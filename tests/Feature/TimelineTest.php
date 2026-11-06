<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CalendarEvent;
use App\Models\Division;
use App\Models\Task;
use App\Models\TaskAssignment;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimelineTest extends TestCase
{
    use RefreshDatabase;

    protected Division $division;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo('2026-11-05 10:00:00');

        $this->division = Division::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<int>
     */
    protected function taskIds(Account $account, array $query = []): array
    {
        return collect($this->actingAs($account)->getJson(route('api.timeline', $query))->assertOk()->json('data.tasks'))
            ->pluck('id')
            ->all();
    }

    public function test_default_period_is_the_current_week(): void
    {
        $this->actingAs(Account::factory()->admin()->create())
            ->getJson(route('api.timeline'))
            ->assertOk()
            ->assertJsonPath('data.period', ['mode' => 'weekly', 'from' => '2026-11-02', 'to' => '2026-11-08']);
    }

    public function test_biweekly_mode_covers_fourteen_days(): void
    {
        $this->actingAs(Account::factory()->admin()->create())
            ->getJson(route('api.timeline', ['mode' => 'biweekly', 'start_date' => '2026-11-09']))
            ->assertJsonPath('data.period', ['mode' => 'biweekly', 'from' => '2026-11-09', 'to' => '2026-11-22']);
    }

    public function test_tasks_overlapping_the_period_are_shown_as_ranges(): void
    {
        $admin = Account::factory()->admin()->create();
        $spanning = Task::factory()->create(['start_date' => '2026-10-28', 'deadline' => '2026-11-03']);
        $inside = Task::factory()->create(['start_date' => '2026-11-04', 'deadline' => '2026-11-06']);
        Task::factory()->create(['start_date' => '2026-11-09', 'deadline' => '2026-11-12']);
        Task::factory()->create(['start_date' => '2026-10-20', 'deadline' => '2026-11-01']);

        $this->assertSame([$spanning->id, $inside->id], $this->taskIds($admin));

        $this->actingAs($admin)
            ->getJson(route('api.timeline'))
            ->assertJsonPath('data.tasks.0.start', '2026-10-28')
            ->assertJsonPath('data.tasks.0.end', '2026-11-03');
    }

    public function test_task_with_a_single_date_is_shown_on_that_day_and_undated_tasks_are_hidden(): void
    {
        $admin = Account::factory()->admin()->create();
        $deadlineOnly = Task::factory()->create(['start_date' => null, 'deadline' => '2026-11-07']);
        $startOnly = Task::factory()->create(['start_date' => '2026-11-02', 'deadline' => null]);
        Task::factory()->create(['start_date' => null, 'deadline' => null]);

        $this->assertSame([$startOnly->id, $deadlineOnly->id], $this->taskIds($admin));

        $this->actingAs($admin)
            ->getJson(route('api.timeline'))
            ->assertJsonPath('data.tasks.1.start', '2026-11-07')
            ->assertJsonPath('data.tasks.1.end', '2026-11-07');
    }

    public function test_staff_sees_tasks_of_other_divisions_without_internal_details(): void
    {
        $employee = Account::factory()->employee()->for($this->division)->create();
        $ownTask = Task::factory()->for($this->division)->create(['start_date' => '2026-11-03', 'deadline' => '2026-11-04']);
        TaskAssignment::factory()->for($ownTask)->for($employee, 'account')->create();
        $otherTask = Task::factory()->create(['start_date' => '2026-11-05', 'deadline' => '2026-11-06']);

        $response = $this->actingAs($employee)->getJson(route('api.timeline'))->assertOk();

        $response->assertJsonPath('data.tasks.0.id', $ownTask->id)
            ->assertJsonPath('data.tasks.0.can_view_detail', true)
            ->assertJsonPath('data.tasks.1.id', $otherTask->id)
            ->assertJsonPath('data.tasks.1.can_view_detail', false)
            ->assertJsonMissingPath('data.tasks.1.description')
            ->assertJsonMissingPath('data.tasks.1.pics');
    }

    public function test_division_filter_limits_tasks_and_keeps_company_wide_events(): void
    {
        $admin = Account::factory()->admin()->create();
        $match = Task::factory()->for($this->division)->create(['start_date' => '2026-11-03', 'deadline' => '2026-11-04']);
        Task::factory()->create(['start_date' => '2026-11-03', 'deadline' => '2026-11-04']);
        $divisionEvent = CalendarEvent::factory()->for($this->division)->create(['start_at' => '2026-11-03 09:00', 'end_at' => '2026-11-03 11:00']);
        $companyEvent = CalendarEvent::factory()->companyWide()->create(['start_at' => '2026-11-04 09:00', 'end_at' => null]);
        CalendarEvent::factory()->create(['start_at' => '2026-11-04 09:00', 'end_at' => '2026-11-04 10:00']);

        $response = $this->actingAs($admin)->getJson(route('api.timeline', ['division_id' => $this->division->id]))->assertOk();

        $this->assertSame([$match->id], collect($response->json('data.tasks'))->pluck('id')->all());
        $this->assertSame([$divisionEvent->id, $companyEvent->id], collect($response->json('data.events'))->pluck('id')->all());
        $response->assertJsonPath('data.events.1.division', null);
    }

    public function test_events_outside_the_period_are_not_shown(): void
    {
        CalendarEvent::factory()->create(['start_at' => '2026-11-10 09:00', 'end_at' => '2026-11-10 10:00']);
        $multiDay = CalendarEvent::factory()->create(['start_at' => '2026-10-30 09:00', 'end_at' => '2026-11-02 17:00']);

        $this->actingAs(Account::factory()->employee()->create())
            ->getJson(route('api.timeline'))
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', $multiDay->id);
    }

    public function test_period_and_mode_are_validated(): void
    {
        $this->actingAs(Account::factory()->admin()->create())
            ->getJson(route('api.timeline', ['start_date' => '05-11-2026', 'mode' => 'monthly']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['start_date', 'mode']);
    }

    public function test_guest_cannot_see_the_timeline(): void
    {
        $this->getJson(route('api.timeline'))->assertUnauthorized();
    }
}
