<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CalendarEvent;
use App\Models\Division;
use App\Models\Task;
use App\Models\TaskAssignment;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalendarTest extends TestCase
{
    use RefreshDatabase;

    protected Division $division;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $this->travelTo('2026-11-05 10:00:00');

        $this->division = Division::factory()->create();
    }

    public function test_month_shows_shared_events_of_every_division_to_every_role(): void
    {
        $event = CalendarEvent::factory()->create(['start_at' => '2026-11-12 09:00', 'end_at' => '2026-11-12 12:00']);
        CalendarEvent::factory()->create(['start_at' => '2026-12-01 09:00', 'end_at' => '2026-12-01 10:00']);

        foreach ([Account::factory()->admin()->create(), Account::factory()->employee()->create()] as $account) {
            $this->actingAs($account)
                ->getJson(route('api.calendar.month', ['month' => '2026-11']))
                ->assertOk()
                ->assertJsonPath('data.month', '2026-11')
                ->assertJsonCount(1, 'data.events')
                ->assertJsonPath('data.events.0.id', $event->id)
                ->assertJsonPath('data.events.0.division.id', $event->division_id);
        }
    }

    public function test_month_defaults_to_the_current_month(): void
    {
        $this->actingAs(Account::factory()->admin()->create())
            ->getJson(route('api.calendar.month'))
            ->assertJsonPath('data.month', '2026-11');
    }

    public function test_month_counts_deadlines_per_date_with_an_overdue_marker(): void
    {
        $admin = Account::factory()->admin()->create();
        Task::factory()->count(2)->create(['deadline' => '2026-11-03', 'status' => 'todo']);
        Task::factory()->done()->create(['deadline' => '2026-11-03']);
        Task::factory()->create(['deadline' => '2026-11-20']);
        Task::factory()->create(['deadline' => null]);

        $this->actingAs($admin)
            ->getJson(route('api.calendar.month', ['month' => '2026-11']))
            ->assertJsonPath('data.deadlines', [
                ['date' => '2026-11-03', 'total' => 3, 'overdue' => 2],
                ['date' => '2026-11-20', 'total' => 1, 'overdue' => 0],
            ]);
    }

    public function test_deadline_markers_include_every_division_for_every_role(): void
    {
        $manager = Account::factory()->manager()->for($this->division)->create();
        Task::factory()->for($this->division)->create(['deadline' => '2026-11-10']);
        Task::factory()->create(['deadline' => '2026-11-10']);

        $this->actingAs($manager)
            ->getJson(route('api.calendar.month', ['month' => '2026-11']))
            ->assertJsonPath('data.deadlines.0.total', 2);
    }

    public function test_deadline_list_of_a_date_contains_the_task_fields(): void
    {
        $admin = Account::factory()->admin()->create();
        $pic = Account::factory()->employee()->for($this->division)->create();
        $task = Task::factory()->for($this->division)->create(['deadline' => '2026-11-04', 'status' => 'in_progress', 'priority' => 'high']);
        TaskAssignment::factory()->for($task)->for($pic, 'account')->create();
        Task::factory()->create(['deadline' => '2026-11-05']);
        $event = CalendarEvent::factory()->companyWide()->create(['start_at' => '2026-11-04 13:00', 'end_at' => null]);

        $this->actingAs($admin)
            ->getJson(route('api.calendar.deadlines', ['date' => '2026-11-04']))
            ->assertOk()
            ->assertJsonPath('message', null)
            ->assertJsonPath('data.date', '2026-11-04')
            ->assertJsonPath('data.events.0.id', $event->id)
            ->assertJsonCount(1, 'data.deadlines')
            ->assertJsonPath('data.deadlines.0.title', $task->title)
            ->assertJsonPath('data.deadlines.0.division.id', $this->division->id)
            ->assertJsonPath('data.deadlines.0.pics.0.id', $pic->id)
            ->assertJsonPath('data.deadlines.0.priority', 'high')
            ->assertJsonPath('data.deadlines.0.deadline', '2026-11-04')
            ->assertJsonPath('data.deadlines.0.status', 'in_progress')
            ->assertJsonPath('data.deadlines.0.is_overdue', true);
    }

    public function test_every_role_sees_the_deadlines_of_every_division_read_only(): void
    {
        $pic = Account::factory()->employee()->for($this->division)->create();
        $assigned = Task::factory()->for($this->division)->create(['deadline' => '2026-11-10']);
        TaskAssignment::factory()->for($assigned)->for($pic, 'account')->create();
        $sameDivision = Task::factory()->for($this->division)->create(['deadline' => '2026-11-10']);
        $otherDivision = Task::factory()->create(['deadline' => '2026-11-10']);

        $accounts = [Account::factory()->admin()->create(), Account::factory()->manager()->for($this->division)->create(), $pic];

        foreach ($accounts as $account) {
            $ids = collect($this->actingAs($account)
                ->getJson(route('api.calendar.deadlines', ['date' => '2026-11-10']))
                ->json('data.deadlines'))->pluck('id')->all();

            $this->assertEqualsCanonicalizing([$assigned->id, $sameDivision->id, $otherDivision->id], $ids);
        }

        $deadlines = collect($this->actingAs($pic)
            ->getJson(route('api.calendar.deadlines', ['date' => '2026-11-10']))
            ->json('data.deadlines'))->keyBy('id');

        $this->assertFalse($deadlines[$otherDivision->id]['abilities']['update']);
    }

    public function test_division_filter_narrows_the_deadline_list(): void
    {
        $manager = Account::factory()->manager()->for($this->division)->create();
        $otherDivision = Division::factory()->create();
        $match = Task::factory()->for($otherDivision)->create(['deadline' => '2026-11-10']);
        Task::factory()->for($this->division)->create(['deadline' => '2026-11-10']);

        $this->actingAs($manager)
            ->getJson(route('api.calendar.deadlines', ['date' => '2026-11-10', 'division_id' => $otherDivision->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data.deadlines')
            ->assertJsonPath('data.deadlines.0.id', $match->id);
    }

    public function test_empty_date_returns_the_no_deadline_message(): void
    {
        $this->actingAs(Account::factory()->admin()->create())
            ->getJson(route('api.calendar.deadlines', ['date' => '2026-11-10']))
            ->assertOk()
            ->assertJsonPath('data.deadlines', [])
            ->assertJsonPath('message', 'Tidak ada deadline tugas pada tanggal ini.');
    }

    public function test_date_and_month_are_validated(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->actingAs($admin)->getJson(route('api.calendar.deadlines'))->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->actingAs($admin)->getJson(route('api.calendar.month', ['month' => '11-2026']))->assertUnprocessable()->assertJsonValidationErrors('month');
    }
}
