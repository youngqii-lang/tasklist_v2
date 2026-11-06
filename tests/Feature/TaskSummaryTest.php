<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Division;
use App\Models\Task;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected Division $division;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $this->travelTo('2026-11-05 10:00:00');

        $this->division = Division::factory()->create(['name' => 'Marketing']);
    }

    public function test_every_kpi_follows_the_calculation_rules(): void
    {
        $admin = Account::factory()->admin()->create();
        $factory = Task::factory()->for($this->division);

        $factory->create(['status' => 'backlog', 'deadline' => null, 'priority' => 'urgent']);
        $factory->create(['status' => 'todo', 'deadline' => '2026-11-05', 'priority' => 'low']);
        $factory->create(['status' => 'in_progress', 'deadline' => '2026-11-01', 'priority' => 'urgent']);
        $factory->create(['status' => 'ready_review', 'deadline' => '2026-11-20', 'priority' => 'low']);
        $factory->create(['status' => 'on_review', 'deadline' => '2026-11-05', 'priority' => 'high']);
        $factory->done()->create(['deadline' => '2026-10-01', 'priority' => 'urgent']);

        $this->actingAs($admin)
            ->getJson(route('api.tasks.summary'))
            ->assertOk()
            ->assertJsonPath('data.scope', ['type' => 'all', 'label' => 'Semua Divisi'])
            ->assertJsonPath('data.metrics', [
                'total' => 6,
                'backlog' => 1,
                'todo' => 1,
                'in_progress' => 1,
                'ready_review' => 1,
                'on_review' => 1,
                'completed' => 1,
                'unfinished' => 5,
                'overdue' => 1,
                'due_today' => 2,
                'urgent' => 2,
            ])
            ->assertJsonPath('data.completion_rate', 16.7);
    }

    public function test_empty_data_returns_zero_without_dividing_by_zero(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson(route('api.tasks.summary'))
            ->assertOk()
            ->assertJsonPath('data.metrics.total', 0)
            ->assertJsonPath('data.metrics.overdue', 0)
            ->assertJsonPath('data.completion_rate', 0);
    }

    public function test_manager_and_staff_see_the_cross_division_report(): void
    {
        Task::factory()->count(2)->for($this->division)->create();
        Task::factory()->count(3)->create();

        foreach ([Account::factory()->manager()->for($this->division)->create(), Account::factory()->employee()->create()] as $account) {
            $this->actingAs($account)
                ->getJson(route('api.tasks.summary'))
                ->assertOk()
                ->assertJsonPath('data.metrics.total', 5);
        }
    }

    public function test_summary_follows_the_division_status_and_priority_filters(): void
    {
        $admin = Account::factory()->admin()->create();
        Task::factory()->for($this->division)->create(['status' => 'todo', 'priority' => 'high']);
        Task::factory()->for($this->division)->create(['status' => 'todo', 'priority' => 'low']);
        Task::factory()->for($this->division)->create(['status' => 'backlog', 'priority' => 'high']);
        Task::factory()->create(['status' => 'todo', 'priority' => 'high']);

        $this->actingAs($admin)
            ->getJson(route('api.tasks.summary', ['division_id' => $this->division->id]))
            ->assertJsonPath('data.scope', ['type' => 'division', 'label' => 'Marketing'])
            ->assertJsonPath('data.metrics.total', 3);

        $this->actingAs($admin)
            ->getJson(route('api.tasks.summary', ['division_id' => $this->division->id, 'status' => 'todo', 'priority' => 'high']))
            ->assertJsonPath('data.metrics.total', 1)
            ->assertJsonPath('data.metrics.todo', 1);
    }

    public function test_deleted_tasks_are_not_counted(): void
    {
        $admin = Account::factory()->admin()->create();
        Task::factory()->create()->delete();

        $this->actingAs($admin)->getJson(route('api.tasks.summary'))->assertJsonPath('data.metrics.total', 0);
    }

    public function test_guest_cannot_see_the_summary(): void
    {
        $this->getJson(route('api.tasks.summary'))->assertUnauthorized();
    }
}
