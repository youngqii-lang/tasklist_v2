<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Division;
use App\Models\Task;
use App\Models\TaskAssignment;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Semua Tugas" and "Tugas Saya" (TASK-12): every active account sees all tasks, while "Tugas Saya"
 * only shows the tasks where the account is an active PIC, in every Tasklist view.
 */
class TaskScopeTest extends TestCase
{
    use RefreshDatabase;

    protected Division $division;

    protected Account $pic;

    protected Account $coPic;

    protected Task $sharedTask;

    protected Task $otherTask;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $this->division = Division::factory()->create();
        $this->pic = Account::factory()->employee()->for($this->division)->create();
        $this->coPic = Account::factory()->freelance()->for($this->division)->create();

        $this->sharedTask = Task::factory()->for($this->division)->create(['start_date' => '2026-12-07', 'deadline' => '2026-12-10']);
        $this->otherTask = Task::factory()->create(['start_date' => '2026-12-08', 'deadline' => '2026-12-10']);

        TaskAssignment::factory()->for($this->sharedTask)->for($this->pic, 'account')->create();
        TaskAssignment::factory()->for($this->sharedTask)->for($this->coPic, 'account')->create();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<int>
     */
    protected function listedIds(Account $account, array $query = []): array
    {
        return collect($this->actingAs($account)->getJson(route('api.tasks.index', $query))->assertOk()->json('data'))
            ->pluck('id')
            ->all();
    }

    public function test_all_tasks_shows_every_division_to_every_role(): void
    {
        foreach (['admin', 'manager', 'supervisor', 'employee', 'freelance'] as $role) {
            $account = Account::factory()->{$role}()->for($this->division)->create();

            $this->assertEqualsCanonicalizing(
                [$this->sharedTask->id, $this->otherTask->id],
                $this->listedIds($account, ['scope' => Task::SCOPE_ALL]),
                "Role {$role} sees the wrong tasks.",
            );
        }
    }

    public function test_my_tasks_only_shows_tasks_where_the_user_is_an_active_pic(): void
    {
        $this->assertSame([$this->sharedTask->id], $this->listedIds($this->pic, ['scope' => Task::SCOPE_MINE]));
        $this->assertSame([], $this->listedIds(Account::factory()->manager()->create(), ['scope' => Task::SCOPE_MINE]));
    }

    public function test_a_multi_pic_task_appears_once_in_the_my_tasks_of_every_pic(): void
    {
        foreach ([$this->pic, $this->coPic] as $account) {
            $response = $this->actingAs($account)
                ->getJson(route('api.tasks.index', ['scope' => Task::SCOPE_MINE]))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $this->sharedTask->id);

            $this->assertSame(1, $response->json('meta.total'));
            $this->assertSame(1, array_sum($response->json('meta.status_counts')));
        }
    }

    public function test_a_released_pic_keeps_seeing_the_task_but_loses_it_from_my_tasks_and_its_rights(): void
    {
        $this->sharedTask->assignments()->where('account_id', $this->coPic->id)->update(['unassigned_at' => now()]);

        $this->assertSame([], $this->listedIds($this->coPic, ['scope' => Task::SCOPE_MINE]));
        $this->assertContains($this->sharedTask->id, $this->listedIds($this->coPic));

        $this->actingAs($this->coPic)
            ->getJson(route('api.tasks.show', $this->sharedTask))
            ->assertOk()
            ->assertJsonPath('data.abilities.update', false)
            ->assertJsonPath('data.abilities.transitions', []);
        $this->actingAs($this->coPic)
            ->patchJson(route('api.tasks.update', $this->sharedTask), ['title' => 'Diubah', 'version' => $this->sharedTask->version])
            ->assertForbidden();
    }

    public function test_summary_follows_the_task_scope(): void
    {
        $this->actingAs($this->pic)
            ->getJson(route('api.tasks.summary', ['scope' => Task::SCOPE_MINE]))
            ->assertOk()
            ->assertJsonPath('data.metrics.total', 1);

        $this->actingAs($this->pic)
            ->getJson(route('api.tasks.summary'))
            ->assertOk()
            ->assertJsonPath('data.metrics.total', 2);
    }

    public function test_calendar_follows_the_task_scope(): void
    {
        $this->actingAs($this->pic)
            ->getJson(route('api.calendar.month', ['month' => '2026-12', 'scope' => Task::SCOPE_MINE]))
            ->assertOk()
            ->assertJsonPath('data.deadlines.0.total', 1);
        $this->actingAs($this->pic)
            ->getJson(route('api.calendar.month', ['month' => '2026-12']))
            ->assertOk()
            ->assertJsonPath('data.deadlines.0.total', 2);

        $this->actingAs($this->pic)
            ->getJson(route('api.calendar.deadlines', ['date' => '2026-12-10', 'scope' => Task::SCOPE_MINE]))
            ->assertOk()
            ->assertJsonCount(1, 'data.deadlines')
            ->assertJsonPath('data.deadlines.0.id', $this->sharedTask->id);
    }

    public function test_timeline_follows_the_task_scope(): void
    {
        $timelineIds = fn (array $query) => collect($this->actingAs($this->pic)
            ->getJson(route('api.timeline', ['start_date' => '2026-12-07'] + $query))
            ->assertOk()
            ->json('data.tasks'))->pluck('id')->all();

        $this->assertSame([$this->sharedTask->id], $timelineIds(['scope' => Task::SCOPE_MINE]));
        $this->assertEqualsCanonicalizing([$this->sharedTask->id, $this->otherTask->id], $timelineIds([]));
    }

    public function test_an_unknown_scope_is_rejected(): void
    {
        $this->actingAs($this->pic)
            ->getJson(route('api.tasks.index', ['scope' => 'everything']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scope');
    }

    public function test_a_deactivated_account_sees_no_tasks_in_any_view(): void
    {
        $this->pic->forceFill(['is_active' => false])->save();

        $this->actingAs($this->pic)->getJson(route('api.tasks.index'))->assertForbidden();
        $this->actingAs($this->pic)->getJson(route('api.timeline'))->assertForbidden();
        $this->actingAs($this->pic)->getJson(route('api.calendar.month'))->assertForbidden();
    }

    public function test_a_manager_who_is_also_pic_may_approve_their_own_task(): void
    {
        $manager = Account::factory()->manager()->create();
        $task = Task::factory()->create(['status' => Task::STATUS_ON_REVIEW, 'requires_review' => true]);
        TaskAssignment::factory()->for($task)->for($manager, 'account')->create();

        $this->actingAs($manager)
            ->postJson(route('api.tasks.reviews.store', $task), ['decision' => 'approved', 'version' => $task->version])
            ->assertOk();

        $this->assertSame(Task::STATUS_DONE, $task->fresh()->status);
    }

    public function test_a_supervisor_who_is_also_pic_may_approve_their_own_task_in_their_division(): void
    {
        $supervisor = Account::factory()->supervisor()->for($this->division)->create();
        $task = Task::factory()->for($this->division)->create(['status' => Task::STATUS_ON_REVIEW, 'requires_review' => true]);
        TaskAssignment::factory()->for($task)->for($supervisor, 'account')->create();

        $this->actingAs($supervisor)
            ->postJson(route('api.tasks.reviews.store', $task), ['decision' => 'approved', 'version' => $task->version])
            ->assertOk();
    }

    public function test_every_active_pic_of_a_multi_pic_task_may_move_it_up_to_ready_review(): void
    {
        $this->sharedTask->update(['status' => Task::STATUS_TODO, 'evidence_required' => false]);

        $this->actingAs($this->pic)
            ->patchJson(route('api.tasks.status.update', $this->sharedTask), ['status' => Task::STATUS_IN_PROGRESS, 'version' => $this->sharedTask->fresh()->version])
            ->assertOk();
        $this->actingAs($this->coPic)
            ->patchJson(route('api.tasks.status.update', $this->sharedTask), ['status' => Task::STATUS_READY_REVIEW, 'version' => $this->sharedTask->fresh()->version])
            ->assertOk();
        $this->actingAs($this->coPic)
            ->patchJson(route('api.tasks.status.update', $this->sharedTask), ['status' => Task::STATUS_ON_REVIEW, 'version' => $this->sharedTask->fresh()->version])
            ->assertForbidden();
    }
}
