<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Division;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskAssignment;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The status workflow follows the TASK-17 transition matrix.
 */
class TaskStatusWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Division $division;

    protected Account $manager;

    protected Account $pic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

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

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function moveTo(Account $account, Task $task, string $status, array $extra = []): TestResponse
    {
        return $this->actingAs($account)->patchJson(route('api.tasks.status.update', $task), [
            'status' => $status,
            'version' => $task->fresh()->version,
        ] + $extra);
    }

    public function test_status_labels_use_the_review_naming(): void
    {
        $this->assertSame([
            'backlog' => 'Back Log',
            'todo' => 'To Do',
            'in_progress' => 'On Process',
            'ready_review' => 'Ready Review',
            'on_review' => 'On Review',
            'done' => 'Done',
        ], Task::STATUS_LABELS);
    }

    public function test_admin_manager_and_supervisor_move_backlog_to_todo(): void
    {
        $accounts = [
            Account::factory()->admin()->create(),
            $this->manager,
            Account::factory()->supervisor()->for($this->division)->create(),
        ];

        foreach ($accounts as $account) {
            $task = $this->task(Task::STATUS_BACKLOG);

            $this->moveTo($account, $task, Task::STATUS_TODO)
                ->assertOk()
                ->assertJsonPath('message', 'Status tugas berhasil diperbarui.')
                ->assertJsonPath('data.status', Task::STATUS_TODO);
        }

        $this->moveTo($this->pic, $this->task(Task::STATUS_BACKLOG), Task::STATUS_TODO)->assertForbidden();
    }

    public function test_backlog_task_needs_a_pic_and_deadline_before_todo(): void
    {
        $task = Task::factory()->for($this->division)->create(['status' => Task::STATUS_BACKLOG]);

        $this->moveTo($this->manager, $task, Task::STATUS_TODO)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Tugas wajib memiliki PIC dan deadline sebelum dipindahkan ke To Do.');
    }

    public function test_pic_and_manager_can_start_and_submit_the_work(): void
    {
        foreach ([$this->pic, $this->manager] as $account) {
            $task = $this->task(Task::STATUS_TODO);

            $this->moveTo($account, $task, Task::STATUS_IN_PROGRESS)->assertOk();
            $this->moveTo($account, $task, Task::STATUS_READY_REVIEW)->assertOk()->assertJsonPath('data.status_label', 'Ready Review');
        }
    }

    public function test_task_without_review_requirement_can_also_be_sent_to_ready_review(): void
    {
        $task = $this->task(Task::STATUS_IN_PROGRESS, ['requires_review' => false]);

        $this->moveTo($this->pic, $task, Task::STATUS_READY_REVIEW)->assertOk();
    }

    public function test_pic_can_never_set_done(): void
    {
        foreach ([[Task::STATUS_IN_PROGRESS, false], [Task::STATUS_ON_REVIEW, true]] as [$status, $requiresReview]) {
            $task = $this->task($status, ['requires_review' => $requiresReview]);

            $this->moveTo($this->pic, $task, Task::STATUS_DONE)
                ->assertForbidden()
                ->assertJsonPath('message', 'Hanya Admin, Manajer, atau Supervisor yang berwenang yang dapat melakukan perpindahan status ini.');

            $this->assertSame($status, $task->fresh()->status);
        }
    }

    public function test_pic_cannot_start_or_decide_the_review(): void
    {
        $readyTask = $this->task(Task::STATUS_READY_REVIEW, ['requires_review' => true]);
        $onReviewTask = $this->task(Task::STATUS_ON_REVIEW, ['requires_review' => true]);

        $this->moveTo($this->pic, $readyTask, Task::STATUS_ON_REVIEW)->assertForbidden();
        $this->moveTo($this->pic, $onReviewTask, Task::STATUS_BACKLOG, ['note' => 'Revisi'])->assertForbidden();
    }

    public function test_manager_finishes_a_task_without_review_straight_from_on_process(): void
    {
        $task = $this->task(Task::STATUS_IN_PROGRESS, ['requires_review' => false]);

        $this->moveTo($this->manager, $task, Task::STATUS_DONE)->assertOk()->assertJsonPath('data.status', Task::STATUS_DONE);

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_task_with_review_requirement_cannot_skip_the_review(): void
    {
        $task = $this->task(Task::STATUS_IN_PROGRESS, ['requires_review' => true]);

        $this->moveTo($this->manager, $task, Task::STATUS_DONE)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Tugas ini wajib review, kirim ke Ready Review terlebih dahulu.');
    }

    public function test_reviewer_starts_the_review_and_approves(): void
    {
        $supervisor = Account::factory()->supervisor()->for($this->division)->create();
        $task = $this->task(Task::STATUS_READY_REVIEW, ['requires_review' => true]);

        $this->moveTo($supervisor, $task, Task::STATUS_ON_REVIEW)->assertOk()->assertJsonPath('data.status_label', 'On Review');
        $this->moveTo($supervisor, $task, Task::STATUS_DONE)->assertOk()->assertJsonPath('data.reviews.0.decision', 'approved');

        $this->assertNotNull($task->fresh()->completed_at);
    }

    public function test_revision_returns_the_task_to_backlog_with_a_note(): void
    {
        $task = $this->task(Task::STATUS_ON_REVIEW, ['requires_review' => true]);

        $this->moveTo($this->manager, $task, Task::STATUS_BACKLOG)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['note' => 'Catatan revisi wajib diisi.']);

        $this->moveTo($this->manager, $task, Task::STATUS_BACKLOG, ['note' => 'Perbaiki warna logo.'])
            ->assertOk()
            ->assertJsonPath('data.status', Task::STATUS_BACKLOG)
            ->assertJsonPath('data.reviews.0.decision', 'revision_requested')
            ->assertJsonPath('data.pics.0.id', $this->pic->id);
    }

    public function test_revised_task_is_reactivated_and_resubmitted_by_the_pic(): void
    {
        $task = $this->task(Task::STATUS_ON_REVIEW, ['requires_review' => true]);

        $this->moveTo($this->manager, $task, Task::STATUS_BACKLOG, ['note' => 'Revisi'])->assertOk();
        $this->moveTo($this->manager, $task, Task::STATUS_TODO)->assertOk();
        $this->moveTo($this->pic, $task, Task::STATUS_IN_PROGRESS)->assertOk();
        $this->moveTo($this->pic, $task, Task::STATUS_READY_REVIEW)->assertOk();

        $this->assertSame(TaskActivity::ACTION_RESUBMITTED, TaskActivity::query()->latest('id')->first()->action);
    }

    public function test_outsiders_cannot_move_the_task(): void
    {
        $task = $this->task(Task::STATUS_TODO);
        $otherStaff = Account::factory()->employee()->for($this->division)->create();
        $otherManager = Account::factory()->manager()->create();

        $this->moveTo($otherStaff, $task, Task::STATUS_IN_PROGRESS)
            ->assertForbidden()
            ->assertJsonPath('message', 'Hanya PIC tugas atau Admin/Manajer/Supervisor yang berwenang yang dapat melakukan perpindahan status ini.');
        $this->moveTo($otherManager, $task, Task::STATUS_IN_PROGRESS)->assertForbidden();

        $this->assertSame(Task::STATUS_TODO, $task->fresh()->status);
    }

    public function test_transitions_outside_the_matrix_are_rejected_with_a_reason(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->moveTo($admin, $this->task(Task::STATUS_BACKLOG), Task::STATUS_DONE)
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Status tidak dapat dipindahkan dari Back Log ke Done.');
        $this->moveTo($admin, $this->task(Task::STATUS_ON_REVIEW), Task::STATUS_IN_PROGRESS)->assertUnprocessable();
        $this->moveTo($admin, $this->task(Task::STATUS_DONE), Task::STATUS_TODO)->assertUnprocessable();
    }

    public function test_status_change_is_recorded_in_the_history(): void
    {
        $task = $this->task(Task::STATUS_TODO);

        $this->moveTo($this->pic, $task, Task::STATUS_IN_PROGRESS)->assertOk();

        $activity = TaskActivity::sole();
        $this->assertSame(TaskActivity::ACTION_STATUS_CHANGED, $activity->action);
        $this->assertSame($this->pic->id, $activity->account_id);
        $this->assertSame(['status' => ['from' => 'todo', 'to' => 'in_progress']], $activity->changes);
        $this->assertNotNull($activity->created_at);
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
        $task = $this->task(Task::STATUS_IN_PROGRESS, ['requires_review' => false]);

        $this->actingAs($this->pic)
            ->getJson(route('api.tasks.show', $task))
            ->assertJsonPath('data.abilities.transitions', [Task::STATUS_READY_REVIEW]);

        $this->actingAs($this->manager)
            ->getJson(route('api.tasks.show', $task))
            ->assertJsonPath('data.abilities.transitions', [Task::STATUS_READY_REVIEW, Task::STATUS_DONE]);

        $reviewTask = $this->task(Task::STATUS_IN_PROGRESS, ['requires_review' => true]);

        $this->actingAs($this->manager)
            ->getJson(route('api.tasks.show', $reviewTask))
            ->assertJsonPath('data.abilities.transitions', [Task::STATUS_READY_REVIEW]);
    }
}
