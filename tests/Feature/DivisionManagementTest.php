<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Division;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DivisionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_sidebar_menu_is_named_organization_management_and_has_account_and_division_tabs(): void
    {
        $employee = Account::factory()->employee()->create();

        $this->actingAs($employee)
            ->get(route('divisions.index'))
            ->assertOk()
            ->assertSee('Organization Management')
            ->assertDontSee('Manajemen Akun')
            ->assertSee('href="'.route('accounts.index').'"', false)
            ->assertSee('href="'.route('divisions.index').'"', false);
    }

    public function test_every_role_can_view_division_list_with_member_counts(): void
    {
        $division = Division::factory()->create(['code_division' => 'FIN', 'name' => 'Keuangan']);
        Account::factory()->for($division)->count(2)->create();

        foreach ([Account::factory()->manager()->create(), Account::factory()->employee()->create()] as $viewer) {
            $this->actingAs($viewer)
                ->get(route('divisions.index'))
                ->assertOk()
                ->assertSee('FIN')
                ->assertSee('Keuangan')
                ->assertSee('2 akun');
        }
    }

    public function test_division_list_shows_summary_cards(): void
    {
        $filled = Division::factory()->create();
        Division::factory()->count(2)->create();
        Account::factory()->for($filled)->count(3)->create();
        $viewer = Account::factory()->manager()->withoutDivision()->create();

        $response = $this->actingAs($viewer)->get(route('divisions.index'))->assertOk();

        $this->assertSame(3, $response->viewData('totalDivisions'));
        $this->assertSame(3, $response->viewData('accountsWithDivision'));
        $this->assertSame(1, $response->viewData('accountsWithoutDivision'));
        $this->assertSame(2, $response->viewData('emptyDivisions'));
        $response->assertSeeInOrder(['Total Divisions', 'Assigned Accounts', 'Unassigned Accounts', 'Empty Divisions']);
    }

    public function test_search_filters_divisions_by_name_or_code(): void
    {
        Division::factory()->create(['code_division' => 'FIN', 'name' => 'Keuangan']);
        Division::factory()->create(['code_division' => 'MKT', 'name' => 'Pemasaran']);
        $viewer = Account::factory()->employee()->create();

        $this->actingAs($viewer)
            ->get(route('divisions.index', ['search' => 'uang']))
            ->assertSee('Keuangan')
            ->assertDontSee('Pemasaran');

        $this->actingAs($viewer)
            ->get(route('divisions.index', ['search' => 'mkt']))
            ->assertSee('Pemasaran')
            ->assertDontSee('Keuangan');
    }

    public function test_employee_cannot_see_management_buttons(): void
    {
        $employee = Account::factory()->employee()->create();
        $division = Division::factory()->create();

        $this->actingAs($employee)
            ->get(route('divisions.index'))
            ->assertOk()
            ->assertDontSee(route('divisions.create'))
            ->assertDontSee(route('divisions.edit', $division))
            ->assertDontSee('data-confirm-delete', false);
    }

    public function test_employee_cannot_access_create_update_or_delete_endpoints(): void
    {
        $employee = Account::factory()->employee()->create();
        $division = Division::factory()->create(['name' => 'Tetap Sama']);

        $this->actingAs($employee)->get(route('divisions.create'))->assertForbidden();
        $this->actingAs($employee)->post(route('divisions.store'), ['code_division' => 'NEW', 'name' => 'Baru'])->assertForbidden();
        $this->actingAs($employee)->get(route('divisions.edit', $division))->assertForbidden();
        $this->actingAs($employee)->put(route('divisions.update', $division), ['code_division' => 'X', 'name' => 'Diubah'])->assertForbidden();
        $this->actingAs($employee)->delete(route('divisions.destroy', $division))->assertForbidden();

        $this->assertDatabaseMissing('divisions', ['code_division' => 'NEW']);
        $this->assertDatabaseHas('divisions', ['id' => $division->id, 'name' => 'Tetap Sama']);
    }

    public function test_manager_can_create_division_with_uppercased_code(): void
    {
        $manager = Account::factory()->manager()->create();

        $this->actingAs($manager)
            ->post(route('divisions.store'), ['code_division' => ' ops ', 'name' => 'Operasional'])
            ->assertRedirect(route('divisions.index'))
            ->assertSessionHas('status');

        $this->assertDatabaseHas('divisions', ['code_division' => 'OPS', 'name' => 'Operasional']);
    }

    public function test_create_rejects_empty_duplicate_and_invalid_values(): void
    {
        $manager = Account::factory()->manager()->create();
        Division::factory()->create(['code_division' => 'FIN', 'name' => 'Keuangan']);

        $this->actingAs($manager)
            ->post(route('divisions.store'), [])
            ->assertSessionHasErrors(['code_division', 'name']);

        $this->actingAs($manager)
            ->post(route('divisions.store'), ['code_division' => 'fin', 'name' => 'Keuangan'])
            ->assertSessionHasErrors(['code_division', 'name']);

        $this->actingAs($manager)
            ->post(route('divisions.store'), ['code_division' => 'A B!', 'name' => str_repeat('a', 26)])
            ->assertSessionHasErrors(['code_division', 'name']);

        $this->assertDatabaseCount('divisions', 2);
    }

    public function test_manager_can_update_division(): void
    {
        $manager = Account::factory()->manager()->create();
        $division = Division::factory()->create(['code_division' => 'OLD', 'name' => 'Lama']);

        $this->actingAs($manager)
            ->put(route('divisions.update', $division), ['code_division' => 'new', 'name' => 'Baru'])
            ->assertRedirect(route('divisions.index'))
            ->assertSessionHas('status');

        $division->refresh();
        $this->assertSame('NEW', $division->code_division);
        $this->assertSame('Baru', $division->name);
    }

    public function test_update_allows_keeping_same_values_but_rejects_another_divisions_values(): void
    {
        $manager = Account::factory()->manager()->create();
        $division = Division::factory()->create(['code_division' => 'FIN', 'name' => 'Keuangan']);
        Division::factory()->create(['code_division' => 'MKT', 'name' => 'Pemasaran']);

        $this->actingAs($manager)
            ->put(route('divisions.update', $division), ['code_division' => 'FIN', 'name' => 'Keuangan'])
            ->assertSessionHasNoErrors();

        $this->actingAs($manager)
            ->put(route('divisions.update', $division), ['code_division' => 'MKT', 'name' => 'Pemasaran'])
            ->assertSessionHasErrors(['code_division', 'name']);
    }

    public function test_manager_can_delete_division_and_members_become_without_division(): void
    {
        $manager = Account::factory()->manager()->withoutDivision()->create();
        $division = Division::factory()->create(['name' => 'Dihapus']);
        $member = Account::factory()->for($division)->create();

        $this->actingAs($manager)
            ->get(route('divisions.index'))
            ->assertSee('data-confirm-delete="Dihapus"', false)
            ->assertSee('1 akun di divisi ini akan menjadi Tanpa Divisi.');

        $this->actingAs($manager)
            ->delete(route('divisions.destroy', $division))
            ->assertRedirect(route('divisions.index'));

        $this->assertDatabaseMissing('divisions', ['id' => $division->id]);
        $this->assertNull($member->refresh()->division_id);
    }
}
