<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Division;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskAssignment;
use App\Models\TaskEvidence;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TaskEvidenceTest extends TestCase
{
    use RefreshDatabase;

    protected Division $division;

    protected Account $pic;

    protected Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        Storage::fake('local');

        $this->division = Division::factory()->create();
        $this->pic = Account::factory()->employee()->for($this->division)->create();
        $this->task = Task::factory()->for($this->division)->create(['status' => Task::STATUS_IN_PROGRESS]);
        TaskAssignment::factory()->for($this->task)->for($this->pic, 'account')->create();
    }

    public function test_pic_can_upload_a_file_as_evidence(): void
    {
        $file = UploadedFile::fake()->create('laporan.pdf', 200, 'application/pdf');

        $response = $this->actingAs($this->pic)
            ->postJson(route('api.tasks.evidences.store', $this->task), ['type' => 'file', 'file' => $file, 'note' => 'Versi final'])
            ->assertCreated()
            ->assertJsonPath('message', 'Evidence berhasil disimpan.')
            ->assertJsonPath('data.type', 'file')
            ->assertJsonPath('data.file_name', 'laporan.pdf')
            ->assertJsonPath('data.note', 'Versi final')
            ->assertJsonPath('data.submitter.id', $this->pic->id);

        $evidence = TaskEvidence::sole();
        $this->assertSame($this->task->id, $evidence->task_id);
        Storage::disk('local')->assertExists($evidence->file_path);
        $this->assertStringStartsWith("task-evidences/{$this->task->id}/", $evidence->file_path);
        $this->assertNotNull($response->json('data.created_at'));
    }

    public function test_pic_can_submit_a_link_as_evidence(): void
    {
        $this->actingAs($this->pic)
            ->postJson(route('api.tasks.evidences.store', $this->task), ['type' => 'link', 'url' => 'https://drive.google.com/file/abc'])
            ->assertCreated()
            ->assertJsonPath('data.url', 'https://drive.google.com/file/abc')
            ->assertJsonPath('data.download_url', null);
    }

    public function test_evidence_submission_is_recorded_in_the_history(): void
    {
        $this->actingAs($this->pic)
            ->postJson(route('api.tasks.evidences.store', $this->task), ['type' => 'link', 'url' => 'https://example.com/hasil'])
            ->assertCreated();

        $activity = TaskActivity::sole();
        $this->assertSame(TaskActivity::ACTION_EVIDENCE_ADDED, $activity->action);
        $this->assertSame('https://example.com/hasil', $activity->changes['evidence']['name']);
    }

    public function test_only_the_current_pic_can_submit_evidence(): void
    {
        $manager = Account::factory()->manager()->for($this->division)->create();
        $otherStaff = Account::factory()->employee()->for($this->division)->create();

        foreach ([$manager, $otherStaff] as $account) {
            $this->actingAs($account)
                ->postJson(route('api.tasks.evidences.store', $this->task), ['type' => 'link', 'url' => 'https://example.com'])
                ->assertForbidden();
        }

        $this->assertDatabaseCount('task_evidences', 0);
    }

    public function test_file_type_and_size_are_validated(): void
    {
        config(['tasklist.evidence.max_size_kb' => 100]);

        $this->actingAs($this->pic)
            ->postJson(route('api.tasks.evidences.store', $this->task), [
                'type' => 'file',
                'file' => UploadedFile::fake()->create('script.php', 10, 'application/x-php'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'Format file tidak diizinkan.']);

        $this->actingAs($this->pic)
            ->postJson(route('api.tasks.evidences.store', $this->task), [
                'type' => 'file',
                'file' => UploadedFile::fake()->create('besar.pdf', 500, 'application/pdf'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'Ukuran file maksimal 100 KB.']);

        $this->assertDatabaseCount('task_evidences', 0);
    }

    public function test_link_must_be_a_valid_url(): void
    {
        $this->actingAs($this->pic)
            ->postJson(route('api.tasks.evidences.store', $this->task), ['type' => 'link', 'url' => 'javascript:alert(1)'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url' => 'Tautan harus berupa URL yang valid (http atau https).']);

        $this->actingAs($this->pic)
            ->postJson(route('api.tasks.evidences.store', $this->task), ['type' => 'video'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('type');
    }

    public function test_evidence_cannot_be_added_during_review_or_after_done(): void
    {
        foreach ([Task::STATUS_ON_REVIEW, Task::STATUS_DONE] as $status) {
            $this->task->update(['status' => $status]);

            $this->actingAs($this->pic)
                ->postJson(route('api.tasks.evidences.store', $this->task), ['type' => 'link', 'url' => 'https://example.com'])
                ->assertUnprocessable()
                ->assertJsonPath('message', 'Evidence tidak dapat diubah saat tugas sedang diperiksa atau sudah selesai.');
        }
    }

    public function test_evidence_is_listed_and_shown_in_the_task_detail(): void
    {
        $manager = Account::factory()->manager()->for($this->division)->create();
        $older = TaskEvidence::factory()->for($this->task)->create(['created_at' => now()->subDay()]);
        $newer = TaskEvidence::factory()->for($this->task)->create();

        $this->actingAs($manager)
            ->getJson(route('api.tasks.evidences.index', $this->task))
            ->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);

        $this->actingAs($manager)
            ->getJson(route('api.tasks.show', $this->task))
            ->assertJsonCount(2, 'data.evidences');
    }

    public function test_read_only_users_can_see_evidence_but_not_add_it(): void
    {
        $otherManager = Account::factory()->manager()->create();
        TaskEvidence::factory()->for($this->task)->create();

        $this->actingAs($otherManager)->getJson(route('api.tasks.evidences.index', $this->task))->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($otherManager)
            ->postJson(route('api.tasks.evidences.store', $this->task), ['type' => 'link', 'url' => 'https://example.com'])
            ->assertForbidden();
    }

    public function test_evidence_file_can_be_downloaded_by_users_with_access(): void
    {
        $this->actingAs($this->pic)->postJson(route('api.tasks.evidences.store', $this->task), [
            'type' => 'file',
            'file' => UploadedFile::fake()->create('laporan.pdf', 10, 'application/pdf'),
        ]);
        $evidence = TaskEvidence::sole();
        $manager = Account::factory()->manager()->for($this->division)->create();

        $this->actingAs($manager)
            ->get(route('api.tasks.evidences.download', [$this->task, $evidence]))
            ->assertOk()
            ->assertDownload('laporan.pdf');

        $this->actingAs(Account::factory()->manager()->inactive()->create())
            ->getJson(route('api.tasks.evidences.download', [$this->task, $evidence]))
            ->assertForbidden();
    }

    public function test_evidence_of_another_task_cannot_be_downloaded_through_this_task(): void
    {
        $admin = Account::factory()->admin()->create();
        $foreignEvidence = TaskEvidence::factory()->file()->create();

        $this->actingAs($admin)
            ->getJson(route('api.tasks.evidences.download', [$this->task, $foreignEvidence]))
            ->assertNotFound();
    }

    public function test_task_that_requires_evidence_cannot_be_submitted_without_it(): void
    {
        $this->task->update(['requires_review' => true, 'evidence_required' => true]);

        $this->actingAs($this->pic)
            ->patchJson(route('api.tasks.status.update', $this->task), ['status' => Task::STATUS_READY_REVIEW, 'version' => 1])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Tugas ini wajib melampirkan evidence sebelum dikirim.');

        TaskEvidence::factory()->for($this->task)->create();

        $this->actingAs($this->pic)
            ->patchJson(route('api.tasks.status.update', $this->task), ['status' => Task::STATUS_READY_REVIEW, 'version' => 1])
            ->assertOk();
    }

    public function test_task_with_optional_evidence_can_be_submitted_without_it(): void
    {
        $this->task->update(['requires_review' => false, 'evidence_required' => false]);

        $this->actingAs($this->pic)
            ->patchJson(route('api.tasks.status.update', $this->task), ['status' => Task::STATUS_READY_REVIEW, 'version' => 1])
            ->assertOk();
    }

    public function test_previous_evidence_stays_available_after_a_revision(): void
    {
        $manager = Account::factory()->manager()->for($this->division)->create();
        $this->task->update(['status' => Task::STATUS_ON_REVIEW, 'requires_review' => true]);
        $firstEvidence = TaskEvidence::factory()->for($this->task)->create();

        $this->actingAs($manager)
            ->patchJson(route('api.tasks.status.update', $this->task), ['status' => Task::STATUS_BACKLOG, 'version' => 1, 'note' => 'Revisi'])
            ->assertOk();

        $this->actingAs($this->pic)
            ->postJson(route('api.tasks.evidences.store', $this->task), ['type' => 'link', 'url' => 'https://example.com/revisi'])
            ->assertCreated();

        $this->actingAs($this->pic)
            ->getJson(route('api.tasks.evidences.index', $this->task))
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.id', $firstEvidence->id);
    }
}
