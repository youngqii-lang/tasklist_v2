<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Division;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskAssignment;
use App\Models\TaskCategory;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TaskRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Division $division;

    protected Task $task;

    protected Account $pic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $this->division = Division::factory()->create();
        $this->task = Task::factory()->for($this->division)->create([
            'status' => Task::STATUS_IN_PROGRESS,
            'title' => 'Judul lama',
            'start_date' => now()->toDateString(),
            'deadline' => now()->addWeek()->toDateString(),
        ]);
        $this->pic = Account::factory()->freelance()->for($this->division)->create();
        TaskAssignment::factory()->for($this->task)->for($this->pic, 'account')->create();
    }

    protected function deleteTask(Account $account, ?Task $task = null): TestResponse
    {
        $task ??= $this->task;

        return $this->actingAs($account)->deleteJson(route('api.tasks.destroy', $task), ['version' => $task->fresh()->version]);
    }

    public function test_pic_can_edit_the_work_details_of_their_own_task(): void
    {
        $this->actingAs($this->pic)
            ->patchJson(route('api.tasks.update', $this->task), [
                'title' => 'Judul dari PIC',
                'description' => 'Catatan pengerjaan',
                'priority' => Task::PRIORITY_HIGH,
                'version' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Judul dari PIC')
            ->assertJsonPath('data.abilities.update', true)
            ->assertJsonPath('data.abilities.assign', false);

        $this->assertSame(TaskActivity::ACTION_UPDATED, TaskActivity::sole()->action);
    }

    public function test_pic_cannot_change_division_category_or_qa_requirement(): void
    {
        $category = TaskCategory::factory()->create();

        $this->actingAs($this->pic)
            ->patchJson(route('api.tasks.update', $this->task), [
                'division_id' => Division::factory()->create()->id,
                'category_id' => $category->id,
                'requires_review' => true,
                'version' => 1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'division_id' => 'Anda tidak berwenang mengubah Divisi.',
                'category_id' => 'Anda tidak berwenang mengubah Kategori.',
                'requires_review' => 'Anda tidak berwenang mengubah Kewajiban QA.',
            ]);
    }

    public function test_staff_cannot_edit_a_task_assigned_to_someone_else(): void
    {
        $otherStaff = Account::factory()->employee()->for($this->division)->create();

        $this->actingAs($otherStaff)
            ->patchJson(route('api.tasks.update', $this->task), ['title' => 'Diubah', 'version' => 1])
            ->assertForbidden();
    }

    public function test_former_pic_loses_the_right_to_edit_after_forwarding(): void
    {
        $supervisor = Account::factory()->supervisor()->for($this->division)->create();
        $newPic = Account::factory()->employee()->for($this->division)->create();

        $this->actingAs($supervisor)
            ->putJson(route('api.tasks.pics.update', $this->task), ['pic_ids' => [$newPic->id], 'version' => 1])
            ->assertOk();

        $this->actingAs($this->pic)
            ->patchJson(route('api.tasks.update', $this->task), ['title' => 'Diubah', 'version' => 2])
            ->assertForbidden();
        $this->actingAs($newPic)
            ->patchJson(route('api.tasks.update', $this->task), ['title' => 'Diubah PIC baru', 'version' => 2])
            ->assertOk();

        $activity = TaskActivity::where('action', TaskActivity::ACTION_PIC_CHANGED)->sole();
        $this->assertSame($supervisor->id, $activity->account_id);
        $this->assertSame($this->pic->id, $activity->changes['pics']['from'][0]['id']);
        $this->assertSame($newPic->id, $activity->changes['pics']['to'][0]['id']);
    }

    public function test_pic_editing_does_not_grant_workflow_manager_transitions(): void
    {
        $this->task->update(['status' => Task::STATUS_IN_PROGRESS, 'requires_review' => false]);

        $this->actingAs($this->pic)
            ->getJson(route('api.tasks.show', $this->task))
            ->assertJsonPath('data.abilities.review', false);

        $backlogTask = Task::factory()->for($this->division)->create(['status' => Task::STATUS_BACKLOG]);
        TaskAssignment::factory()->for($backlogTask)->for($this->pic, 'account')->create();

        $this->actingAs($this->pic)
            ->patchJson(route('api.tasks.status.update', $backlogTask), ['status' => Task::STATUS_TODO, 'version' => 1])
            ->assertForbidden();
    }

    public function test_managers_and_supervisors_can_soft_delete_tasks_of_their_division(): void
    {
        foreach (['manager', 'supervisor'] as $role) {
            $task = Task::factory()->for($this->division)->create();
            $account = Account::factory()->{$role}()->for($this->division)->create();

            $this->deleteTask($account, $task)
                ->assertOk()
                ->assertJsonPath('message', 'Tugas berhasil dihapus.');

            $this->assertSoftDeleted('tasks', ['id' => $task->id, 'deleted_by' => $account->id]);
        }
    }

    public function test_manager_cannot_delete_tasks_of_another_division(): void
    {
        $this->deleteTask(Account::factory()->manager()->create())->assertForbidden();

        $this->assertNotSoftDeleted('tasks', ['id' => $this->task->id]);
    }

    public function test_pic_can_delete_their_own_task_but_not_others(): void
    {
        $otherTask = Task::factory()->for($this->division)->create();

        $this->deleteTask($this->pic, $otherTask)->assertForbidden();
        $this->deleteTask($this->pic)->assertOk();

        $this->assertSoftDeleted('tasks', ['id' => $this->task->id, 'deleted_by' => $this->pic->id]);
    }

    public function test_deleted_task_disappears_from_the_list_and_keeps_its_history(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->deleteTask($admin)->assertOk();

        $this->actingAs($admin)->getJson(route('api.tasks.index'))->assertJsonPath('data', []);
        $this->actingAs($admin)->getJson(route('api.tasks.show', $this->task))->assertNotFound();
        $this->assertDatabaseHas('task_activities', [
            'task_id' => $this->task->id,
            'account_id' => $admin->id,
            'action' => TaskActivity::ACTION_DELETED,
        ]);
        $this->assertDatabaseHas('task_assignments', ['task_id' => $this->task->id, 'account_id' => $this->pic->id]);
    }

    public function test_delete_requires_the_current_version(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->actingAs($admin)->deleteJson(route('api.tasks.destroy', $this->task))->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->actingAs($admin)->deleteJson(route('api.tasks.destroy', $this->task), ['version' => 9])->assertConflict();

        $this->assertNotSoftDeleted('tasks', ['id' => $this->task->id]);
    }

    public function test_task_exposes_the_delete_ability(): void
    {
        $this->actingAs($this->pic)->getJson(route('api.tasks.show', $this->task))->assertJsonPath('data.abilities.delete', true);
        $this->actingAs(Account::factory()->employee()->create())
            ->getJson(route('api.tasks.show', $this->task))
            ->assertJsonPath('data.abilities.delete', false);
    }
}
