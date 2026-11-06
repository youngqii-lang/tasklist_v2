<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Division;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskAssignment;
use App\Models\TaskReview;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TaskReviewTest extends TestCase
{
    use RefreshDatabase;

    protected Division $division;

    protected Account $manager;

    protected Account $pic;

    protected Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $this->division = Division::factory()->create();
        $this->manager = Account::factory()->manager()->for($this->division)->create();
        $this->pic = Account::factory()->employee()->for($this->division)->create();
        $this->task = Task::factory()->for($this->division)->create([
            'status' => Task::STATUS_ON_REVIEW,
            'requires_review' => true,
        ]);
        TaskAssignment::factory()->for($this->task)->for($this->pic, 'account')->create();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function decide(Account $account, array $data): TestResponse
    {
        return $this->actingAs($account)->postJson(
            route('api.tasks.reviews.store', $this->task),
            $data + ['version' => $this->task->fresh()->version],
        );
    }

    public function test_manager_starts_the_review_of_a_submitted_task(): void
    {
        $this->task->update(['status' => Task::STATUS_READY_REVIEW]);

        $this->actingAs($this->manager)
            ->patchJson(route('api.tasks.status.update', $this->task), ['status' => Task::STATUS_ON_REVIEW, 'version' => 1])
            ->assertOk()
            ->assertJsonPath('data.status', Task::STATUS_ON_REVIEW);
    }

    public function test_approval_finishes_the_task_and_stores_the_decision(): void
    {
        $this->decide($this->manager, ['decision' => 'approved', 'note' => 'Sudah sesuai.'])
            ->assertOk()
            ->assertJsonPath('message', 'Keputusan review berhasil disimpan.')
            ->assertJsonPath('data.status', Task::STATUS_DONE)
            ->assertJsonPath('data.reviews.0.decision', 'approved')
            ->assertJsonPath('data.reviews.0.reviewer.id', $this->manager->id);

        $this->assertNotNull($this->task->fresh()->completed_at);
        $this->assertDatabaseHas('task_reviews', [
            'task_id' => $this->task->id,
            'reviewer_id' => $this->manager->id,
            'decision' => 'approved',
            'note' => 'Sudah sesuai.',
        ]);
        $this->assertSame(TaskActivity::ACTION_APPROVED, TaskActivity::sole()->action);
    }

    public function test_revision_returns_the_task_to_backlog_with_a_note(): void
    {
        $this->decide($this->manager, ['decision' => 'revision_requested', 'note' => 'Ukuran gambar salah.'])
            ->assertOk()
            ->assertJsonPath('data.status', Task::STATUS_BACKLOG)
            ->assertJsonPath('data.reviews.0.note', 'Ukuran gambar salah.');

        $activity = TaskActivity::sole();
        $this->assertSame(TaskActivity::ACTION_REVISION_REQUESTED, $activity->action);
        $this->assertSame('Ukuran gambar salah.', $activity->changes['note']);
    }

    public function test_revision_without_a_note_is_rejected(): void
    {
        $this->decide($this->manager, ['decision' => 'revision_requested'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['note' => 'Catatan revisi wajib diisi.']);

        $this->assertSame(Task::STATUS_ON_REVIEW, $this->task->fresh()->status);
        $this->assertDatabaseCount('task_reviews', 0);
    }

    public function test_decision_is_only_possible_when_the_task_is_on_review(): void
    {
        $this->task->update(['status' => Task::STATUS_READY_REVIEW]);

        $this->decide($this->manager, ['decision' => 'approved'])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Keputusan review hanya dapat diberikan pada tugas berstatus On Review.');
    }

    public function test_staff_cannot_review_their_own_task(): void
    {
        $this->decide($this->pic, ['decision' => 'approved'])->assertForbidden();

        $this->assertSame(Task::STATUS_ON_REVIEW, $this->task->fresh()->status);
    }

    public function test_manager_cannot_review_tasks_of_another_division_but_admin_can(): void
    {
        $this->decide(Account::factory()->manager()->create(), ['decision' => 'approved'])->assertForbidden();
        $this->decide(Account::factory()->admin()->create(), ['decision' => 'approved'])->assertOk();
    }

    public function test_review_cycle_can_repeat_and_keeps_every_decision(): void
    {
        $this->decide($this->manager, ['decision' => 'revision_requested', 'note' => 'Revisi pertama'])->assertOk();

        $steps = [
            [$this->manager, Task::STATUS_TODO],
            [$this->pic, Task::STATUS_IN_PROGRESS],
            [$this->pic, Task::STATUS_READY_REVIEW],
            [$this->manager, Task::STATUS_ON_REVIEW],
        ];

        foreach ($steps as [$account, $status]) {
            $this->actingAs($account)
                ->patchJson(route('api.tasks.status.update', $this->task), ['status' => $status, 'version' => $this->task->fresh()->version])
                ->assertOk();
        }

        $this->decide($this->manager, ['decision' => 'approved'])->assertOk()->assertJsonPath('data.status', Task::STATUS_DONE);

        $this->assertSame(
            ['approved', 'revision_requested'],
            TaskReview::query()->latest('id')->pluck('decision')->all(),
        );
    }

    public function test_pic_can_read_the_review_history(): void
    {
        TaskReview::factory()->revisionRequested()->for($this->task)->create(['note' => 'Tolong revisi.']);

        $this->actingAs($this->pic)
            ->getJson(route('api.tasks.reviews.index', $this->task))
            ->assertOk()
            ->assertJsonPath('data.0.decision_label', 'Request Revision')
            ->assertJsonPath('data.0.note', 'Tolong revisi.');
    }

    public function test_decision_must_be_valid(): void
    {
        $this->decide($this->manager, ['decision' => 'rejected'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('decision');
    }
}
