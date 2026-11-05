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

class TaskEvidenceFormatTest extends TestCase
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
        $this->pic = Account::factory()->freelance()->for($this->division)->create();
        $this->task = Task::factory()->for($this->division)->create(['status' => Task::STATUS_IN_PROGRESS]);
        TaskAssignment::factory()->for($this->task)->for($this->pic, 'account')->create();
    }

    protected function upload(UploadedFile $file): TaskEvidence
    {
        $id = $this->actingAs($this->pic)
            ->postJson(route('api.tasks.evidences.store', $this->task), ['type' => 'file', 'file' => $file])
            ->assertCreated()
            ->json('data.id');

        return TaskEvidence::findOrFail($id);
    }

    public function test_word_pdf_and_image_files_are_accepted(): void
    {
        $files = [
            UploadedFile::fake()->create('brief.doc', 20, 'application/msword'),
            UploadedFile::fake()->create('brief.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            UploadedFile::fake()->create('laporan.pdf', 20, 'application/pdf'),
            UploadedFile::fake()->image('foto.jpg'),
            UploadedFile::fake()->image('foto.jpeg'),
            UploadedFile::fake()->image('desain.png'),
            UploadedFile::fake()->image('desain.webp'),
        ];

        foreach ($files as $file) {
            $this->upload($file);
        }

        $this->assertDatabaseCount('task_evidences', 7);
        $this->assertSame(Task::STATUS_IN_PROGRESS, $this->task->fresh()->status);
    }

    public function test_other_and_dangerous_formats_are_rejected(): void
    {
        $files = [
            UploadedFile::fake()->create('data.xlsx', 20, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
            UploadedFile::fake()->create('script.php', 1, 'application/x-php'),
            UploadedFile::fake()->create('halaman.html', 1, 'text/html'),
            UploadedFile::fake()->create('logo.svg', 1, 'image/svg+xml'),
            UploadedFile::fake()->create('virus.exe', 1, 'application/x-msdownload'),
        ];

        foreach ($files as $file) {
            $this->actingAs($this->pic)
                ->postJson(route('api.tasks.evidences.store', $this->task), ['type' => 'file', 'file' => $file])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['file' => 'Format file tidak diizinkan.']);
        }

        $this->assertDatabaseCount('task_evidences', 0);
    }

    public function test_link_can_have_a_title_used_as_its_name(): void
    {
        $this->actingAs($this->pic)
            ->postJson(route('api.tasks.evidences.store', $this->task), [
                'type' => 'link',
                'url' => 'https://www.figma.com/file/abc',
                'title' => 'Desain final di Figma',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Desain final di Figma')
            ->assertJsonPath('data.name', 'Desain final di Figma')
            ->assertJsonPath('data.preview_url', null);

        $this->actingAs($this->pic)
            ->postJson(route('api.tasks.evidences.store', $this->task), ['type' => 'link', 'url' => 'https://example.com/hasil'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'https://example.com/hasil');
    }

    public function test_image_evidence_has_a_preview_and_documents_only_download(): void
    {
        $image = $this->upload(UploadedFile::fake()->image('foto.png'));
        $document = $this->upload(UploadedFile::fake()->create('brief.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'));
        $reader = Account::factory()->employee()->create();

        $response = $this->actingAs($reader)->getJson(route('api.tasks.evidences.index', $this->task))->assertOk();
        $items = collect($response->json('data'))->keyBy('id');

        $this->assertTrue($items[$image->id]['is_image']);
        $this->assertNotNull($items[$image->id]['preview_url']);
        $this->assertFalse($items[$document->id]['is_image']);
        $this->assertNull($items[$document->id]['preview_url']);
        $this->assertNotNull($items[$document->id]['download_url']);

        $this->actingAs($reader)
            ->get(route('api.tasks.evidences.preview', [$this->task, $image]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->actingAs($reader)->get(route('api.tasks.evidences.preview', [$this->task, $document]))->assertNotFound();
        $this->actingAs($reader)
            ->get(route('api.tasks.evidences.download', [$this->task, $document]))
            ->assertOk()
            ->assertDownload('brief.docx');
    }

    public function test_evidence_shows_uploader_and_time_and_the_card_shows_the_count(): void
    {
        TaskEvidence::factory()->count(2)->for($this->task)->for($this->pic, 'submitter')->create();

        $this->actingAs($this->pic)
            ->getJson(route('api.tasks.show', $this->task))
            ->assertJsonPath('data.evidences.0.submitter.id', $this->pic->id)
            ->assertJsonStructure(['data' => ['evidences' => [['created_at']]]]);

        $this->actingAs($this->pic)
            ->getJson(route('api.tasks.index'))
            ->assertJsonPath('data.0.evidences_count', 2);
    }

    public function test_pic_can_delete_their_own_evidence(): void
    {
        $evidence = $this->upload(UploadedFile::fake()->image('salah.jpg'));

        $this->actingAs($this->pic)
            ->deleteJson(route('api.tasks.evidences.destroy', [$this->task, $evidence]))
            ->assertOk()
            ->assertJsonPath('message', 'Evidence berhasil dihapus.');

        $this->assertSoftDeleted('task_evidences', ['id' => $evidence->id, 'deleted_by' => $this->pic->id]);
        Storage::disk('local')->assertExists($evidence->file_path);
        $this->assertSame(TaskActivity::ACTION_EVIDENCE_DELETED, TaskActivity::query()->latest('id')->first()->action);
        $this->actingAs($this->pic)->getJson(route('api.tasks.evidences.index', $this->task))->assertJsonPath('data', []);
    }

    public function test_evidence_cannot_be_deleted_by_others_or_by_a_former_pic(): void
    {
        $evidence = TaskEvidence::factory()->for($this->task)->for($this->pic, 'submitter')->create();
        $otherPic = Account::factory()->employee()->for($this->division)->create();
        TaskAssignment::factory()->for($this->task)->for($otherPic, 'account')->create();
        $manager = Account::factory()->manager()->for($this->division)->create();

        foreach ([$otherPic, $manager] as $account) {
            $this->actingAs($account)
                ->deleteJson(route('api.tasks.evidences.destroy', [$this->task, $evidence]))
                ->assertForbidden();
        }

        $this->task->assignments()->where('account_id', $this->pic->id)->update(['unassigned_at' => now()]);

        $this->actingAs($this->pic)
            ->deleteJson(route('api.tasks.evidences.destroy', [$this->task, $evidence]))
            ->assertForbidden();

        $this->assertNotSoftDeleted('task_evidences', ['id' => $evidence->id]);
    }

    public function test_evidence_cannot_be_deleted_during_review_or_after_done(): void
    {
        $evidence = TaskEvidence::factory()->for($this->task)->for($this->pic, 'submitter')->create();
        $this->task->update(['status' => Task::STATUS_ON_REVIEW]);

        $this->actingAs($this->pic)
            ->deleteJson(route('api.tasks.evidences.destroy', [$this->task, $evidence]))
            ->assertUnprocessable();

        $this->assertNotSoftDeleted('task_evidences', ['id' => $evidence->id]);
    }

    public function test_evidence_stays_available_after_done(): void
    {
        $evidence = TaskEvidence::factory()->for($this->task)->create();
        $this->task->update(['status' => Task::STATUS_DONE]);

        $this->actingAs(Account::factory()->admin()->create())
            ->getJson(route('api.tasks.evidences.index', $this->task))
            ->assertJsonPath('data.0.id', $evidence->id)
            ->assertJsonPath('data.0.can_delete', false);
    }

    public function test_empty_evidence_list_returns_an_empty_array(): void
    {
        $this->actingAs($this->pic)->getJson(route('api.tasks.evidences.index', $this->task))->assertOk()->assertJsonPath('data', []);
    }
}
