<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Division;
use App\Models\Role;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccountManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_guest_cannot_access_account_management(): void
    {
        $this->get(route('accounts.index'))->assertRedirect(route('login'));
    }

    public function test_every_role_can_view_account_list_with_fullname_and_division(): void
    {
        $division = Division::factory()->create(['name' => 'Keuangan']);
        Account::factory()->for($division)->create(['fullname' => 'Siti Aminah']);

        foreach ([Account::factory()->manager()->create(), Account::factory()->employee()->create()] as $viewer) {
            $this->actingAs($viewer)
                ->get(route('accounts.index'))
                ->assertOk()
                ->assertSee('Siti Aminah')
                ->assertSee('Keuangan');
        }
    }

    public function test_account_list_shows_total_and_per_role_count_cards(): void
    {
        $viewer = Account::factory()->manager()->create();
        Account::factory()->manager()->count(2)->create();
        Account::factory()->employee()->count(4)->create();

        $response = $this->actingAs($viewer)->get(route('accounts.index'))->assertOk();

        $this->assertSame(7, $response->viewData('totalAccounts'));
        $this->assertSame(
            ['Admin' => 0, 'Employee' => 4, 'Freelance' => 0, 'Manager' => 3, 'Supervisor' => 0],
            $response->viewData('roles')->pluck('accounts_count', 'name')->all(),
        );

        $response->assertSeeInOrder(['Total Accounts', '7', 'Employee Accounts', '4', 'Manager Accounts', '3']);
    }

    public function test_division_filter_lists_divisions_from_database_with_all_divisions_default(): void
    {
        Division::factory()->create(['name' => 'Pemasaran']);
        $manager = Account::factory()->manager()->create();

        $this->actingAs($manager)
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertSeeInOrder(['Semua Divisi', 'Pemasaran']);
    }

    public function test_division_filter_only_shows_accounts_in_selected_division(): void
    {
        $finance = Division::factory()->create(['name' => 'Keuangan']);
        $marketing = Division::factory()->create(['name' => 'Pemasaran']);
        Account::factory()->for($finance)->create(['fullname' => 'Akun Keuangan']);
        Account::factory()->for($marketing)->create(['fullname' => 'Akun Pemasaran']);
        $employee = Account::factory()->employee()->for($finance)->create(['fullname' => 'Pegawai Penonton']);

        $this->actingAs($employee)
            ->get(route('accounts.index', ['division' => $finance->id]))
            ->assertOk()
            ->assertSee('Akun Keuangan')
            ->assertDontSee('Akun Pemasaran');
    }

    public function test_employee_cannot_see_management_buttons(): void
    {
        $employee = Account::factory()->employee()->create();
        $other = Account::factory()->create();

        $this->actingAs($employee)
            ->get(route('accounts.index'))
            ->assertOk()
            ->assertDontSee(route('accounts.create'))
            ->assertDontSee(route('accounts.edit', $other))
            ->assertDontSee('data-confirm-delete', false);
    }

    public function test_employee_cannot_access_create_update_or_delete_endpoints(): void
    {
        $employee = Account::factory()->employee()->create();
        $other = Account::factory()->create(['fullname' => 'Tetap Sama']);
        $division = Division::factory()->create();

        $this->actingAs($employee)->get(route('accounts.create'))->assertForbidden();
        $this->actingAs($employee)->post(route('accounts.store'), [
            'fullname' => 'Penyusup',
            'username' => 'penyusup',
            'password' => 'password123',
            'division_id' => $division->id,
        ])->assertForbidden();
        $this->actingAs($employee)->get(route('accounts.edit', $other))->assertForbidden();
        $this->actingAs($employee)->put(route('accounts.update', $other), [
            'fullname' => 'Diubah',
            'username' => 'diubah',
            'role_id' => $other->role_id,
            'division_id' => $division->id,
        ])->assertForbidden();
        $this->actingAs($employee)->delete(route('accounts.destroy', $other))->assertForbidden();

        $this->assertDatabaseMissing('accounts', ['username' => 'penyusup']);
        $this->assertDatabaseHas('accounts', ['id' => $other->id, 'fullname' => 'Tetap Sama']);
    }

    public function test_manager_create_form_only_has_fullname_username_password_and_division(): void
    {
        $manager = Account::factory()->manager()->create();

        $response = $this->actingAs($manager)->get(route('accounts.create'))->assertOk();

        foreach (['fullname', 'username', 'password', 'division_id'] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }

        $response->assertDontSee('name="role_id"', false);
    }

    public function test_admin_create_form_has_role_field_with_every_role(): void
    {
        $admin = Account::factory()->admin()->create();

        $response = $this->actingAs($admin)->get(route('accounts.create'))->assertOk();

        $response->assertSee('name="role_id"', false)->assertSee('Pilih role');

        foreach (Role::pluck('name') as $roleName) {
            $response->assertSee($roleName);
        }
    }

    #[DataProvider('roleCodes')]
    public function test_admin_can_create_account_with_chosen_role(string $roleCode): void
    {
        $admin = Account::factory()->admin()->create();
        $division = Division::factory()->create();

        $this->actingAs($admin)->post(route('accounts.store'), [
            'fullname' => 'Akun Baru',
            'username' => 'akunbaru',
            'password' => 'rahasia123',
            'role_id' => Role::where('code_role', $roleCode)->value('id'),
            'division_id' => $division->id,
        ])->assertRedirect(route('accounts.index'))->assertSessionHas('status', 'Akun Akun Baru berhasil dibuat.');

        $this->assertSame($roleCode, Account::where('username', 'akunbaru')->firstOrFail()->role->code_role);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function roleCodes(): array
    {
        return [
            'admin' => [Role::ADMIN],
            'manager' => [Role::MANAGER],
            'supervisor' => [Role::SUPERVISOR],
            'employee' => [Role::EMPLOYEE],
            'freelance' => [Role::FREELANCE],
        ];
    }

    public function test_admin_must_choose_a_valid_role(): void
    {
        $admin = Account::factory()->admin()->create();
        $payload = [
            'fullname' => 'Akun Baru',
            'username' => 'akunbaru',
            'password' => 'rahasia123',
            'division_id' => Division::factory()->create()->id,
        ];

        $this->actingAs($admin)->post(route('accounts.store'), $payload)->assertSessionHasErrors('role_id');
        $this->actingAs($admin)->post(route('accounts.store'), [...$payload, 'role_id' => 999])->assertSessionHasErrors('role_id');

        $this->assertDatabaseMissing('accounts', ['username' => 'akunbaru']);
    }

    public function test_manager_cannot_choose_role_when_creating_account(): void
    {
        $manager = Account::factory()->manager()->create();

        $this->actingAs($manager)->post(route('accounts.store'), [
            'fullname' => 'Akun Baru',
            'username' => 'akunbaru',
            'password' => 'rahasia123',
            'role_id' => Role::where('code_role', Role::ADMIN)->value('id'),
            'division_id' => Division::factory()->create()->id,
        ])->assertRedirect(route('accounts.index'));

        $this->assertSame(Role::EMPLOYEE, Account::where('username', 'akunbaru')->firstOrFail()->role->code_role);
    }

    public function test_admin_can_update_and_delete_other_accounts_but_not_delete_itself(): void
    {
        $admin = Account::factory()->admin()->create();
        $other = Account::factory()->employee()->create();

        $this->actingAs($admin)->get(route('accounts.index'))
            ->assertSee(route('accounts.create'))
            ->assertSee(route('accounts.edit', $other));

        $this->actingAs($admin)->put(route('accounts.update', $other), [
            'fullname' => 'Sudah Diubah',
            'username' => $other->username,
            'role_id' => Role::where('code_role', Role::SUPERVISOR)->value('id'),
            'division_id' => Division::factory()->create()->id,
        ])->assertRedirect(route('accounts.index'));

        $this->assertSame(Role::SUPERVISOR, $other->fresh()->role->code_role);
        $this->assertSame('Sudah Diubah', $other->fresh()->fullname);

        $this->actingAs($admin)->delete(route('accounts.destroy', $admin))->assertForbidden();
        $this->actingAs($admin)->delete(route('accounts.destroy', $other))->assertRedirect(route('accounts.index'));

        $this->assertModelMissing($other);
        $this->assertModelExists($admin);
    }

    public function test_manager_can_create_active_employee_account_with_hashed_password(): void
    {
        $manager = Account::factory()->manager()->create();
        $division = Division::factory()->create();

        $this->actingAs($manager)->post(route('accounts.store'), [
            'fullname' => 'Budi Santoso',
            'username' => 'budi123',
            'password' => 'rahasia123',
            'division_id' => $division->id,
        ])->assertRedirect(route('accounts.index'))->assertSessionHas('status');

        $account = Account::where('username', 'budi123')->firstOrFail();

        $this->assertSame('Budi Santoso', $account->fullname);
        $this->assertSame($division->id, $account->division_id);
        $this->assertSame(Role::EMPLOYEE, $account->role->code_role);
        $this->assertNotSame('rahasia123', $account->getRawOriginal('password'));
        $this->assertTrue(Hash::check('rahasia123', $account->password));

        auth()->logout();

        $this->post(route('login.store'), ['username' => 'budi123', 'password' => 'rahasia123'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($account);
    }

    public function test_create_rejects_empty_fields(): void
    {
        $manager = Account::factory()->manager()->create();

        $this->actingAs($manager)
            ->post(route('accounts.store'), [])
            ->assertSessionHasErrors(['fullname', 'username', 'password', 'division_id']);

        $this->assertDatabaseCount('accounts', 1);
    }

    public function test_create_rejects_duplicate_username(): void
    {
        $manager = Account::factory()->manager()->create();
        Account::factory()->create(['username' => 'budi']);

        $this->actingAs($manager)->post(route('accounts.store'), [
            'fullname' => 'Budi Lain',
            'username' => 'budi',
            'password' => 'password123',
            'division_id' => Division::factory()->create()->id,
        ])->assertSessionHasErrors('username');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidUsernames(): array
    {
        return [
            'contains space' => ['budi santoso'],
            'contains symbol' => ['budi@123'],
            'contains underscore' => ['budi_santoso'],
            'contains dot' => ['budi.santoso'],
        ];
    }

    #[DataProvider('invalidUsernames')]
    public function test_create_rejects_username_with_spaces_or_symbols(string $username): void
    {
        $manager = Account::factory()->manager()->create();

        $this->actingAs($manager)->post(route('accounts.store'), [
            'fullname' => 'Budi Santoso',
            'username' => $username,
            'password' => 'password123',
            'division_id' => Division::factory()->create()->id,
        ])->assertSessionHasErrors('username');

        $this->assertDatabaseMissing('accounts', ['username' => $username]);
    }

    public function test_manager_can_update_account_fields_including_role_and_password(): void
    {
        $manager = Account::factory()->manager()->create();
        $account = Account::factory()->employee()->create();
        $newDivision = Division::factory()->create(['name' => 'Divisi Baru']);
        $managerRole = Role::where('code_role', Role::MANAGER)->firstOrFail();

        $this->actingAs($manager)->put(route('accounts.update', $account), [
            'fullname' => 'Nama Baru',
            'username' => 'namabaru',
            'password' => 'passwordbaru',
            'role_id' => $managerRole->id,
            'division_id' => $newDivision->id,
        ])->assertRedirect(route('accounts.index'))->assertSessionHas('status');

        $account->refresh();

        $this->assertSame('Nama Baru', $account->fullname);
        $this->assertSame('namabaru', $account->username);
        $this->assertTrue($account->isManager());
        $this->assertSame($newDivision->id, $account->division_id);
        $this->assertTrue(Hash::check('passwordbaru', $account->password));

        $this->actingAs($manager)
            ->get(route('accounts.index'))
            ->assertSee('Nama Baru')
            ->assertSee('Divisi Baru');
    }

    public function test_manager_can_update_another_manager(): void
    {
        $manager = Account::factory()->manager()->create();
        $otherManager = Account::factory()->manager()->create();

        $this->actingAs($manager)->put(route('accounts.update', $otherManager), [
            'fullname' => 'Manager Diperbarui',
            'username' => $otherManager->username,
            'role_id' => $otherManager->role_id,
            'division_id' => Division::factory()->create()->id,
        ])->assertRedirect(route('accounts.index'));

        $this->assertSame('Manager Diperbarui', $otherManager->refresh()->fullname);
    }

    public function test_update_keeps_password_when_left_empty(): void
    {
        $manager = Account::factory()->manager()->create();
        $account = Account::factory()->create();
        $originalHash = $account->password;

        $this->actingAs($manager)->put(route('accounts.update', $account), [
            'fullname' => 'Tanpa Ganti Password',
            'username' => $account->username,
            'password' => '',
            'role_id' => $account->role_id,
            'division_id' => $account->division_id,
        ])->assertSessionHasNoErrors();

        $this->assertSame($originalHash, $account->refresh()->password);
    }

    public function test_update_rejects_empty_fields_and_invalid_username(): void
    {
        $manager = Account::factory()->manager()->create();
        $account = Account::factory()->create();

        $this->actingAs($manager)
            ->put(route('accounts.update', $account), ['username' => 'ada spasi'])
            ->assertSessionHasErrors(['fullname', 'username', 'role_id', 'division_id']);
    }

    public function test_update_rejects_username_taken_by_another_account(): void
    {
        $manager = Account::factory()->manager()->create();
        $account = Account::factory()->create();
        Account::factory()->create(['username' => 'sudahdipakai']);

        $this->actingAs($manager)->put(route('accounts.update', $account), [
            'fullname' => $account->fullname,
            'username' => 'sudahdipakai',
            'role_id' => $account->role_id,
            'division_id' => $account->division_id,
        ])->assertSessionHasErrors('username');
    }

    public function test_manager_sees_delete_confirmation_dialog(): void
    {
        $manager = Account::factory()->manager()->create();
        Account::factory()->create();

        $this->actingAs($manager)
            ->get(route('accounts.index'))
            ->assertSee('data-confirm-delete', false)
            ->assertSee('Apakah Anda yakin ingin menghapus akun');
    }

    public function test_manager_can_hard_delete_employee_and_manager_accounts(): void
    {
        $manager = Account::factory()->manager()->create();
        $employee = Account::factory()->employee()->create(['fullname' => 'Akun Dihapus']);
        $otherManager = Account::factory()->manager()->create();

        $this->actingAs($manager)->delete(route('accounts.destroy', $employee))->assertRedirect(route('accounts.index'));
        $this->actingAs($manager)->delete(route('accounts.destroy', $otherManager))->assertRedirect(route('accounts.index'));

        $this->assertDatabaseMissing('accounts', ['id' => $employee->id]);
        $this->assertDatabaseMissing('accounts', ['id' => $otherManager->id]);

        $this->actingAs($manager)->get(route('accounts.index'))->assertDontSee('Akun Dihapus');
    }

    public function test_manager_cannot_delete_own_account(): void
    {
        $manager = Account::factory()->manager()->create();

        $this->actingAs($manager)
            ->get(route('accounts.index'))
            ->assertDontSee('action="'.route('accounts.destroy', $manager).'"', false);

        $this->actingAs($manager)->delete(route('accounts.destroy', $manager))->assertForbidden();

        $this->assertDatabaseHas('accounts', ['id' => $manager->id]);
    }
}
