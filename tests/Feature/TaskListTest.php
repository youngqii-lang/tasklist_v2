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

class TaskListTest extends TestCase
{
    use RefreshDatabase;

    protected Division $division;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $this->division = Division::factory()->create();
    }

    /**
     * Get the ids of the tasks returned by the list endpoint.
     *
     * @param  array<string, mixed>  $query
     * @return list<int>
     */
    protected function listedIds(Account $account, array $query = []): array
    {
        return collect($this->actingAs($account)->getJson(route('api.tasks.index', $query))->assertOk()->json('data'))
            ->pluck('id')
            ->all();
    }

    public function test_admin_sees_tasks_of_every_division(): void
    {
        $admin = Account::factory()->admin()->withoutDivision()->create();
        $tasks = Task::factory()->count(2)->create();

        $this->assertEqualsCanonicalizing($tasks->modelKeys(), $this->listedIds($admin));
    }

    public function test_every_role_sees_the_tasks_of_every_division(): void
    {
        $ownTask = Task::factory()->for($this->division)->create();
        $otherTask = Task::factory()->create();

        foreach (['manager', 'supervisor', 'employee', 'freelance'] as $role) {
            $account = Account::factory()->{$role}()->for($this->division)->create();

            $this->assertEqualsCanonicalizing([$ownTask->id, $otherTask->id], $this->listedIds($account), "Role {$role} sees the wrong tasks.");
        }
    }

    public function test_tasks_outside_the_users_authority_are_read_only(): void
    {
        $employee = Account::factory()->employee()->for($this->division)->create();
        $task = Task::factory()->for($this->division)->create();
        TaskAssignment::factory()->for(Task::factory()->create())->for($employee, 'account')->create();

        $abilities = collect($this->actingAs($employee)->getJson(route('api.tasks.index'))->json('data'))
            ->firstWhere('id', $task->id)['abilities'];

        $this->assertFalse($abilities['update']);
        $this->assertFalse($abilities['assign']);
        $this->assertSame([], $abilities['transitions']);
    }

    public function test_deactivated_account_cannot_list_tasks(): void
    {
        $this->actingAs(Account::factory()->employee()->inactive()->create())
            ->getJson(route('api.tasks.index'))
            ->assertForbidden();
    }

    public function test_task_card_contains_the_board_fields(): void
    {
        $admin = Account::factory()->admin()->create();
        $pic = Account::factory()->employee()->for($this->division)->create();
        $task = Task::factory()->for($this->division)->create([
            'status' => Task::STATUS_READY_REVIEW,
            'priority' => Task::PRIORITY_URGENT,
            'deadline' => '2026-12-01',
        ]);
        TaskAssignment::factory()->for($task)->for($pic, 'account')->create();

        $this->actingAs($admin)
            ->getJson(route('api.tasks.index'))
            ->assertOk()
            ->assertJsonPath('data.0.title', $task->title)
            ->assertJsonPath('data.0.division.name', $this->division->name)
            ->assertJsonPath('data.0.pics.0.fullname', $pic->fullname)
            ->assertJsonPath('data.0.priority_label', 'Urgent')
            ->assertJsonPath('data.0.deadline', '2026-12-01')
            ->assertJsonPath('data.0.status', Task::STATUS_READY_REVIEW)
            ->assertJsonPath('data.0.status_label', 'Ready Review');
    }

    public function test_tasks_can_be_searched_by_title(): void
    {
        $admin = Account::factory()->admin()->create();
        $match = Task::factory()->create(['title' => 'Desain banner promo']);
        Task::factory()->create(['title' => 'Laporan bulanan']);

        $this->assertSame([$match->id], $this->listedIds($admin, ['search' => 'banner']));
    }

    public function test_tasks_can_be_filtered_by_division_priority_and_status(): void
    {
        $admin = Account::factory()->admin()->create();
        $match = Task::factory()->for($this->division)->create(['priority' => 'high', 'status' => 'todo']);
        Task::factory()->for($this->division)->create(['priority' => 'low', 'status' => 'todo']);
        Task::factory()->for($this->division)->create(['priority' => 'high', 'status' => 'backlog']);
        Task::factory()->create(['priority' => 'high', 'status' => 'todo']);

        $this->assertSame([$match->id], $this->listedIds($admin, [
            'division_id' => $this->division->id,
            'priority' => 'high',
            'status' => 'todo',
        ]));
    }

    public function test_status_counts_cover_every_status_and_follow_the_filters(): void
    {
        $admin = Account::factory()->admin()->create();
        Task::factory()->count(2)->for($this->division)->create(['status' => 'todo']);
        Task::factory()->for($this->division)->done()->create();
        Task::factory()->create(['status' => 'todo']);

        $this->actingAs($admin)
            ->getJson(route('api.tasks.index', ['division_id' => $this->division->id]))
            ->assertOk()
            ->assertJsonPath('meta.status_counts', [
                'backlog' => 0,
                'todo' => 2,
                'in_progress' => 0,
                'ready_review' => 0,
                'on_review' => 0,
                'done' => 1,
            ])
            ->assertJsonPath('meta.total', 3);
    }

    public function test_status_counts_cover_every_division(): void
    {
        $manager = Account::factory()->manager()->for($this->division)->create();
        Task::factory()->for($this->division)->create(['status' => 'todo']);
        Task::factory()->count(3)->create(['status' => 'todo']);

        $this->actingAs($manager)
            ->getJson(route('api.tasks.index'))
            ->assertJsonPath('meta.status_counts.todo', 4);
    }

    public function test_priority_is_sorted_by_severity(): void
    {
        $admin = Account::factory()->admin()->create();
        $low = Task::factory()->create(['priority' => 'low']);
        $urgent = Task::factory()->create(['priority' => 'urgent']);
        $medium = Task::factory()->create(['priority' => 'medium']);

        $this->assertSame(
            [$urgent->id, $medium->id, $low->id],
            $this->listedIds($admin, ['sort' => 'priority', 'direction' => 'desc']),
        );
    }

    public function test_tasks_can_be_sorted_by_deadline(): void
    {
        $admin = Account::factory()->admin()->create();
        $later = Task::factory()->create(['deadline' => '2026-12-20']);
        $sooner = Task::factory()->create(['deadline' => '2026-12-01']);

        $this->assertSame([$sooner->id, $later->id], $this->listedIds($admin, ['sort' => 'deadline', 'direction' => 'asc']));
    }

    public function test_list_is_paginated(): void
    {
        $admin = Account::factory()->admin()->create();
        Task::factory()->count(3)->create();

        $this->actingAs($admin)
            ->getJson(route('api.tasks.index', ['per_page' => 2]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2);
    }

    public function test_soft_deleted_tasks_are_not_listed(): void
    {
        $admin = Account::factory()->admin()->create();
        Task::factory()->create()->delete();

        $this->assertSame([], $this->listedIds($admin));
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson(route('api.tasks.index', [
                'status' => 'archived',
                'priority' => 'critical',
                'sort' => 'password',
                'per_page' => 500,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'priority', 'sort', 'per_page']);
    }

    public function test_guest_cannot_list_tasks(): void
    {
        $this->getJson(route('api.tasks.index'))->assertUnauthorized();
    }
}
