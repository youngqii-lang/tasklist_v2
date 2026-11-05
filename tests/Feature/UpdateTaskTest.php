<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Division;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskCategory;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UpdateTaskTest extends TestCase
{
    use RefreshDatabase;

    protected Division $division;

    protected Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $this->division = Division::factory()->create();
        $this->task = Task::factory()->for($this->division)->create([
            'title' => 'Judul lama',
            'priority' => Task::PRIORITY_LOW,
            'start_date' => '2026-11-10',
            'deadline' => '2026-11-20',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function patchTask(Account $account, array $data, ?Task $task = null): TestResponse
    {
        $task ??= $this->task;

        return $this->actingAs($account)->patchJson(route('api.tasks.update', $task), $data + ['version' => $task->version]);
    }

    public function test_admin_can_update_the_main_information(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->patchTask($admin, ['title' => 'Judul baru', 'priority' => Task::PRIORITY_HIGH])
            ->assertOk()
            ->assertJsonPath('message', 'Tugas berhasil diperbarui.')
            ->assertJsonPath('data.title', 'Judul baru')
            ->assertJsonPath('data.priority', Task::PRIORITY_HIGH)
            ->assertJsonPath('data.version', 2);

        $this->assertDatabaseHas('tasks', ['id' => $this->task->id, 'title' => 'Judul baru', 'version' => 2]);
    }

    public function test_manager_can_update_tasks_of_their_division_only(): void
    {
        $manager = Account::factory()->manager()->for($this->division)->create();
        $otherTask = Task::factory()->create();

        $this->patchTask($manager, ['title' => 'Judul baru'])->assertOk();
        $this->patchTask($manager, ['title' => 'Judul baru'], $otherTask)->assertForbidden();
    }

    public function test_staff_who_is_not_the_pic_cannot_update_the_task(): void
    {
        $employee = Account::factory()->employee()->for($this->division)->create();

        $this->patchTask($employee, ['title' => 'Judul baru'])->assertForbidden();

        $this->assertDatabaseHas('tasks', ['id' => $this->task->id, 'title' => 'Judul lama']);
    }

    public function test_only_admin_can_move_a_task_to_another_division(): void
    {
        $manager = Account::factory()->manager()->for($this->division)->create();
        $admin = Account::factory()->admin()->create();
        $otherDivision = Division::factory()->create();

        $this->patchTask($manager, ['division_id' => $otherDivision->id])->assertForbidden();
        $this->patchTask($admin, ['division_id' => $otherDivision->id])
            ->assertOk()
            ->assertJsonPath('data.division.id', $otherDivision->id);
    }

    public function test_changes_are_recorded_with_before_and_after_values(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->patchTask($admin, ['title' => 'Judul baru', 'deadline' => '2026-11-25'])
            ->assertOk()
            ->assertJsonPath('data.activities.0.action', TaskActivity::ACTION_UPDATED)
            ->assertJsonPath('data.activities.0.account.id', $admin->id);

        $activity = TaskActivity::sole();
        $this->assertSame([
            'title' => ['from' => 'Judul lama', 'to' => 'Judul baru'],
            'deadline' => ['from' => '2026-11-20', 'to' => '2026-11-25'],
        ], $activity->changes);
    }

    public function test_saving_without_changes_keeps_the_version_and_history(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->patchTask($admin, ['title' => 'Judul lama', 'start_date' => '2026-11-10'])
            ->assertOk()
            ->assertJsonPath('data.version', 1);

        $this->assertDatabaseCount('task_activities', 0);
    }

    public function test_stale_version_is_rejected_without_overwriting_data(): void
    {
        $admin = Account::factory()->admin()->create();
        $this->task->update(['version' => 3, 'title' => 'Diubah orang lain']);

        $this->actingAs($admin)
            ->patchJson(route('api.tasks.update', $this->task), ['title' => 'Judul baru', 'version' => 2])
            ->assertConflict()
            ->assertJsonPath('message', 'Tugas sudah diperbarui oleh pengguna lain. Muat ulang data lalu coba lagi.');

        $this->assertDatabaseHas('tasks', ['id' => $this->task->id, 'title' => 'Diubah orang lain']);
    }

    public function test_version_is_required(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->actingAs($admin)
            ->patchJson(route('api.tasks.update', $this->task), ['title' => 'Judul baru'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['version' => 'Versi data tugas wajib dikirim.']);
    }

    public function test_fields_are_validated(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->patchTask($admin, ['title' => '', 'priority' => 'critical', 'category_id' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'priority', 'category_id']);
    }

    public function test_deadline_cannot_be_before_the_start_date(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->patchTask($admin, ['start_date' => '2026-11-22'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['deadline' => 'Deadline tidak boleh sebelum tanggal mulai.']);
    }

    public function test_changed_deadline_cannot_be_in_the_past(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->patchTask($admin, ['deadline' => now()->subDay()->toDateString(), 'start_date' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['deadline' => 'Deadline tidak boleh sebelum hari ini.']);
    }

    public function test_running_task_must_keep_a_deadline(): void
    {
        $admin = Account::factory()->admin()->create();
        $this->task->update(['status' => Task::STATUS_TODO]);

        $this->patchTask($admin, ['deadline' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['deadline' => 'Deadline wajib diisi untuk tugas yang sudah berjalan.']);
    }

    public function test_status_cannot_be_changed_through_the_edit_endpoint(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->patchTask($admin, ['status' => Task::STATUS_DONE, 'title' => 'Judul baru'])->assertOk();

        $this->assertSame(Task::STATUS_BACKLOG, $this->task->fresh()->status);
    }

    public function test_qa_requirement_follows_the_new_category(): void
    {
        $admin = Account::factory()->admin()->create();
        $category = TaskCategory::factory()->create(['requires_review' => true, 'evidence_required' => true]);

        $this->patchTask($admin, ['category_id' => $category->id, 'requires_review' => false])
            ->assertOk()
            ->assertJsonPath('data.requires_review', true)
            ->assertJsonPath('data.evidence_required', true);
    }

    public function test_qa_requirement_cannot_change_during_review(): void
    {
        $admin = Account::factory()->admin()->create();
        $this->task->update(['status' => Task::STATUS_ON_REVIEW, 'requires_review' => true]);

        $this->patchTask($admin, ['requires_review' => false])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Kategori dan kewajiban review tidak dapat diubah saat tugas sedang diperiksa atau sudah selesai.');

        $this->assertTrue($this->task->fresh()->requires_review);
    }

    public function test_deleted_task_cannot_be_updated(): void
    {
        $admin = Account::factory()->admin()->create();
        $this->task->delete();

        $this->patchTask($admin, ['title' => 'Judul baru'])->assertNotFound();
    }
}
