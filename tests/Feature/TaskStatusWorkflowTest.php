<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Division;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskAssignment;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TaskStatusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Division $division;

    protected Account $manager;

    protected Account $pic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->division = Division::factory()->create();
        $this->manager = Account::factory()->manager()->for($this->division)->create();
        $this->pic = Account::factory()->employee()->for($this->division)->create();
    }

    /**
     * Create a task of the test division with the test PIC assigned.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function task(string $status, array $attributes = []): Task
    {
        $task = Task::factory()->for($this->division)->create(['status' => $status] + $attributes);
        TaskAssignment::factory()->for($task)->for($this->pic, 'account')->create();

        return $task;
    }

    protected function moveTo(Account $account, Task $task, string $status): TestResponse
    {
        return $this->actingAs($account)->patchJson(route('api.tasks.status.update', $task), [
            'status' => $status,
            'version' => $task->fresh()->version,
        ]);
    }

    public function test_manager_moves_backlog_to_todo(): void
    {
        $task = $this->task(Task::STATUS_BACKLOG);

        $this->moveTo($this->manager, $task, Task::STATUS_TODO)
            ->assertOk()
            ->assertJsonPath('message', 'Status tugas berhasil diperbarui.')
            ->assertJsonPath('data.status', Task::STATUS_TODO)
            ->assertJsonPath('data.version', 2);
    }

    public function test_backlog_task_needs_a_pic_and_deadline_before_todo(): void
    {
        $task = Task::factory()->for($this->division)->create(['status' => Task::STATUS_BACKLOG]);

        $this->moveTo($this->manager, $task, Task::STATUS_TODO)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Tugas wajib memiliki PIC dan deadline sebelum dipindahkan ke To Do.');
    }

    public function test_pic_starts_and_finishes_a_task_without_qa(): void
    {
        $task = $this->task(Task::STATUS_TODO, ['requires_review' => false]);

        $this->moveTo($this->pic, $task, Task::STATUS_IN_PROGRESS)->assertOk();
        $this->moveTo($this->pic, $task, Task::STATUS_DONE)->assertOk()->assertJsonPath('data.status', Task::STATUS_DONE);

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_pic_cannot_skip_qa_on_a_task_that_requires_it(): void
    {
        $task = $this->task(Task::STATUS_IN_PROGRESS, ['requires_review' => true]);

        $this->moveTo($this->pic, $task, Task::STATUS_DONE)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Tugas ini wajib QA, kirim ke Ready for QA terlebih dahulu.');

        $this->moveTo($this->pic, $task, Task::STATUS_READY_REVIEW)->assertOk();
    }

    public function test_task_without_qa_cannot_be_sent_to_review(): void
    {
        $task = $this->task(Task::STATUS_IN_PROGRESS, ['requires_review' => false]);

        $this->moveTo($this->pic, $task, Task::STATUS_READY_REVIEW)->assertUnprocessable();
    }

    public function test_manager_runs_the_review_steps(): void
    {
        $task = $this->task(Task::STATUS_READY_REVIEW, ['requires_review' => true]);

        $this->moveTo($this->manager, $task, Task::STATUS_ON_REVIEW)->assertOk();
        $this->moveTo($this->manager, $task, Task::STATUS_DONE)->assertOk();

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_manager_can_send_a_reviewed_task_back_to_process(): void
    {
        $task = $this->task(Task::STATUS_ON_REVIEW, ['requires_review' => true]);

        $this->moveTo($this->manager, $task, Task::STATUS_IN_PROGRESS)
            ->assertOk()
            ->assertJsonPath('data.status', Task::STATUS_IN_PROGRESS);
    }

    public function test_pic_cannot_do_the_manager_transitions(): void
    {
        foreach ([[Task::STATUS_BACKLOG, Task::STATUS_TODO], [Task::STATUS_READY_REVIEW, Task::STATUS_ON_REVIEW], [Task::STATUS_ON_REVIEW, Task::STATUS_DONE]] as [$from, $to]) {
            $task = $this->task($from, ['requires_review' => true]);

            $this->moveTo($this->pic, $task, $to)
                ->assertForbidden()
                ->assertJsonPath('message', 'Hanya Admin atau Manajer divisi yang dapat melakukan perpindahan status ini.');

            $this->assertSame($from, $task->fresh()->status);
        }
    }

    public function test_manager_cannot_do_the_pic_transitions(): void
    {
        $task = $this->task(Task::STATUS_TODO);

        $this->moveTo($this->manager, $task, Task::STATUS_IN_PROGRESS)
            ->assertForbidden()
            ->assertJsonPath('message', 'Hanya PIC tugas yang dapat melakukan perpindahan status ini.');
    }

    public function test_other_staff_cannot_move_the_task(): void
    {
        $task = $this->task(Task::STATUS_TODO);
        $otherStaff = Account::factory()->employee()->for($this->division)->create();

        $this->moveTo($otherStaff, $task, Task::STATUS_IN_PROGRESS)->assertForbidden();
        $this->assertSame(Task::STATUS_TODO, $task->fresh()->status);
    }

    public function test_manager_of_another_division_cannot_move_the_task(): void
    {
        $task = $this->task(Task::STATUS_BACKLOG);
        $otherManager = Account::factory()->manager()->create();

        $this->moveTo($otherManager, $task, Task::STATUS_TODO)->assertForbidden();
    }

    public function test_transitions_outside_the_matrix_are_rejected_with_a_reason(): void
    {
        $admin = Account::factory()->admin()->create();
        $task = $this->task(Task::STATUS_BACKLOG);

        $this->moveTo($admin, $task, Task::STATUS_DONE)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Status tidak dapat dipindahkan dari Back Log ke Done.');

        $done = $this->task(Task::STATUS_DONE);
        $this->moveTo($admin, $done, Task::STATUS_TODO)->assertUnprocessable();
    }

    public function test_status_change_is_recorded_in_the_history(): void
    {
        $task = $this->task(Task::STATUS_TODO);

        $this->moveTo($this->pic, $task, Task::STATUS_IN_PROGRESS)->assertOk();

        $activity = TaskActivity::sole();
        $this->assertSame(TaskActivity::ACTION_STATUS_CHANGED, $activity->action);
        $this->assertSame($this->pic->id, $activity->account_id);
        $this->assertSame(['status' => ['from' => 'todo', 'to' => 'in_progress']], $activity->changes);
    }

    public function test_change_based_on_stale_data_is_rejected(): void
    {
        $task = $this->task(Task::STATUS_TODO);

        $this->actingAs($this->pic)
            ->patchJson(route('api.tasks.status.update', $task), ['status' => Task::STATUS_IN_PROGRESS, 'version' => 5])
            ->assertConflict();

        $this->assertSame(Task::STATUS_TODO, $task->fresh()->status);
    }

    public function test_status_and_version_are_validated(): void
    {
        $task = $this->task(Task::STATUS_TODO);

        $this->actingAs($this->pic)
            ->patchJson(route('api.tasks.status.update', $task), ['status' => 'archived'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'version']);
    }

    public function test_task_exposes_the_transitions_the_user_may_make(): void
    {
        $task = $this->task(Task::STATUS_IN_PROGRESS, ['requires_review' => true]);

        $this->actingAs($this->pic)
            ->getJson(route('api.tasks.show', $task))
            ->assertJsonPath('data.abilities.transitions', [Task::STATUS_READY_REVIEW]);

        $this->actingAs($this->manager)
            ->getJson(route('api.tasks.show', $task))
            ->assertJsonPath('data.abilities.transitions', []);
    }
}
