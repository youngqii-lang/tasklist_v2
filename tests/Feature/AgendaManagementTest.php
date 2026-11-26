<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CalendarEvent;
use App\Models\Division;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AgendaManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Division $division;

    protected Division $otherDivision;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $this->division = Division::factory()->create();
        $this->otherDivision = Division::factory()->create();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'title' => 'Rapat evaluasi bulanan',
            'description' => 'Ruang meeting lantai 2.',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-02',
            'is_shared' => true,
            ...$overrides,
        ];
    }

    public function test_admin_adds_a_shared_agenda_that_everyone_sees(): void
    {
        $admin = Account::factory()->admin()->withoutDivision()->create();
        $staff = Account::factory()->employee()->for($this->otherDivision)->create();

        $this->actingAs($admin)->postJson(route('api.agendas.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.title', 'Rapat evaluasi bulanan')
            ->assertJsonPath('data.is_shared', true)
            ->assertJsonPath('data.start_date', '2026-12-01')
            ->assertJsonPath('data.end_date', '2026-12-02')
            ->assertJsonPath('data.can_update', true);

        $this->assertDatabaseHas('calendar_events', ['title' => 'Rapat evaluasi bulanan', 'creator_id' => $admin->id]);

        $this->actingAs($staff)->getJson(route('api.tasks.calendar', ['month' => '2026-12']))
            ->assertOk()
            ->assertJsonPath('data.events.0.title', 'Rapat evaluasi bulanan')
            ->assertJsonPath('data.events.0.can_update', false)
            ->assertJsonPath('data.events.0.can_delete', false);
    }

    public function test_one_day_agenda_is_stored_without_an_end(): void
    {
        $admin = Account::factory()->admin()->withoutDivision()->create();

        $this->actingAs($admin)->postJson(route('api.agendas.store'), $this->payload(['end_date' => '2026-12-01']))
            ->assertCreated()
            ->assertJsonPath('data.end_date', null);
    }

    public function test_supervisor_adds_an_internal_agenda_for_their_own_division(): void
    {
        $supervisor = Account::factory()->supervisor()->for($this->division)->create();
        $staffHere = Account::factory()->employee()->for($this->division)->create();
        $staffElsewhere = Account::factory()->employee()->for($this->otherDivision)->create();

        $this->actingAs($supervisor)->postJson(route('api.agendas.store'), $this->payload([
            'is_shared' => false,
            'target_division_ids' => [$this->division->id],
        ]))->assertCreated()
            ->assertJsonPath('data.division.code_division', $this->division->code_division)
            ->assertJsonPath('data.target_divisions.0.code_division', $this->division->code_division);

        $this->actingAs($staffHere)->getJson(route('api.tasks.calendar', ['month' => '2026-12']))
            ->assertJsonCount(1, 'data.events');
        $this->actingAs($staffElsewhere)->getJson(route('api.tasks.calendar', ['month' => '2026-12']))
            ->assertJsonCount(0, 'data.events');
    }

    public function test_supervisor_cannot_organise_or_target_another_division(): void
    {
        $supervisor = Account::factory()->supervisor()->for($this->division)->create();

        $this->actingAs($supervisor)->postJson(route('api.agendas.store'), $this->payload(['division_id' => $this->otherDivision->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('division_id');

        $this->actingAs($supervisor)->postJson(route('api.agendas.store'), $this->payload([
            'is_shared' => false,
            'target_division_ids' => [$this->division->id, $this->otherDivision->id],
        ]))->assertUnprocessable()->assertJsonValidationErrors('target_division_ids');

        $this->assertDatabaseCount('calendar_events', 0);
    }

    public function test_manager_internal_agenda_must_include_their_own_division(): void
    {
        $manager = Account::factory()->manager()->for($this->division)->create();

        $this->actingAs($manager)->postJson(route('api.agendas.store'), $this->payload([
            'is_shared' => false,
            'target_division_ids' => [$this->otherDivision->id],
        ]))->assertUnprocessable()->assertJsonValidationErrors('target_division_ids');

        $this->actingAs($manager)->postJson(route('api.agendas.store'), $this->payload([
            'is_shared' => false,
            'division_id' => $this->otherDivision->id,
            'target_division_ids' => [$this->division->id, $this->otherDivision->id],
        ]))->assertCreated()->assertJsonCount(2, 'data.target_divisions');
    }

    public function test_staff_freelance_and_inactive_accounts_cannot_add_agendas(): void
    {
        foreach ([
            Account::factory()->employee()->for($this->division)->create(),
            Account::factory()->freelance()->for($this->division)->create(),
            Account::factory()->manager()->inactive()->for($this->division)->create(),
        ] as $account) {
            $this->actingAs($account)->postJson(route('api.agendas.store'), $this->payload())->assertForbidden();
        }

        $this->assertDatabaseCount('calendar_events', 0);
    }

    public function test_guest_cannot_add_agendas(): void
    {
        $this->postJson(route('api.agendas.store'), $this->payload())->assertUnauthorized();
    }

    public function test_agenda_fields_are_validated(): void
    {
        $admin = Account::factory()->admin()->withoutDivision()->create();

        $this->actingAs($admin)->postJson(route('api.agendas.store'), [
            'title' => '',
            'start_date' => '2026-12-05',
            'end_date' => '2026-12-01',
            'is_shared' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors(['title', 'end_date', 'target_division_ids']);
    }

    public function test_supervisor_edits_and_deletes_only_their_divisions_agendas(): void
    {
        $supervisor = Account::factory()->supervisor()->for($this->division)->create();
        $own = CalendarEvent::factory()->create(['division_id' => $this->division->id, 'is_shared' => true]);
        $other = CalendarEvent::factory()->create(['division_id' => $this->otherDivision->id, 'is_shared' => true]);

        $this->actingAs($supervisor)->putJson(route('api.agendas.update', $own), $this->payload(['title' => 'Rapat dipindah']))
            ->assertOk()
            ->assertJsonPath('data.title', 'Rapat dipindah');

        $this->actingAs($supervisor)->putJson(route('api.agendas.update', $other), $this->payload())->assertForbidden();
        $this->actingAs($supervisor)->deleteJson(route('api.agendas.destroy', $other))->assertForbidden();

        $this->actingAs($supervisor)->deleteJson(route('api.agendas.destroy', $own))->assertOk();
        $this->assertSoftDeleted($own);
        $this->assertNotSoftDeleted($other);
    }

    public function test_changing_an_agenda_to_shared_clears_its_target_divisions(): void
    {
        $manager = Account::factory()->manager()->for($this->division)->create();
        $event = CalendarEvent::factory()->internalTo($this->division, $this->otherDivision)->create();

        $this->actingAs($manager)->putJson(route('api.agendas.update', $event), $this->payload(['is_shared' => true]))
            ->assertOk()
            ->assertJsonPath('data.is_shared', true)
            ->assertJsonPath('data.target_divisions', []);

        $this->assertDatabaseMissing('calendar_event_divisions', ['calendar_event_id' => $event->id]);
    }

    public function test_agendas_hidden_from_an_account_cannot_be_changed_by_it(): void
    {
        $manager = Account::factory()->manager()->for($this->division)->create();
        $hidden = CalendarEvent::factory()->internalTo($this->otherDivision)->create();

        $this->actingAs($manager)->putJson(route('api.agendas.update', $hidden), $this->payload())->assertForbidden();
        $this->actingAs($manager)->deleteJson(route('api.agendas.destroy', $hidden))->assertForbidden();
    }

    public function test_tasklist_page_tells_who_may_add_agendas(): void
    {
        $supervisor = Account::factory()->supervisor()->for($this->division)->create();
        $staff = Account::factory()->employee()->for($this->division)->create();

        $this->actingAs($supervisor)->get(route('tasks.index'))
            ->assertViewHas('config', fn (array $config) => $config['agenda']['can_create'] === true
                && $config['agenda']['own_division_id'] === $this->division->id
                && $config['urls']['agendas'] === route('api.agendas.store'));

        $this->actingAs($staff)->get(route('tasks.index'))
            ->assertViewHas('config', fn (array $config) => $config['agenda']['can_create'] === false);
    }
}
