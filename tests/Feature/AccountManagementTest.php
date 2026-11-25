<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Division;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskComment;
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

    public function test_manager_can_update_account_fields_but_not_its_role(): void
    {
        $manager = Account::factory()->manager()->create();
        $account = Account::factory()->employee()->create();
        $newDivision = Division::factory()->create(['name' => 'Divisi Baru']);
        $adminRole = Role::where('code_role', Role::ADMIN)->firstOrFail();

        $this->actingAs($manager)->put(route('accounts.update', $account), [
            'fullname' => 'Nama Baru',
            'username' => 'namabaru',
            'password' => 'passwordbaru',
            'role_id' => $adminRole->id,
            'division_id' => $newDivision->id,
        ])->assertRedirect(route('accounts.index'))->assertSessionHas('status');

        $account->refresh();

        $this->assertSame('Nama Baru', $account->fullname);
        $this->assertSame('namabaru', $account->username);
        $this->assertSame(Role::EMPLOYEE, $account->role->code_role);
        $this->assertSame($newDivision->id, $account->division_id);
        $this->assertTrue(Hash::check('passwordbaru', $account->password));

        $this->actingAs($manager)
            ->get(route('accounts.index'))
            ->assertSee('Nama Baru')
            ->assertSee('Divisi Baru');
    }

    public function test_manager_can_update_staff_and_freelance_accounts(): void
    {
        $manager = Account::factory()->manager()->create();

        foreach ([Account::factory()->employee()->create(), Account::factory()->freelance()->create()] as $account) {
            $this->actingAs($manager)->put(route('accounts.update', $account), [
                'fullname' => 'Staff Diperbarui',
                'username' => $account->username,
                'division_id' => $account->division_id,
            ])->assertRedirect(route('accounts.index'));

            $this->assertSame('Staff Diperbarui', $account->refresh()->fullname);
        }
    }

    public function test_manager_cannot_open_or_update_admin_manager_or_supervisor_accounts(): void
    {
        $manager = Account::factory()->manager()->create();
        $division = Division::factory()->create();

        foreach (['admin', 'manager', 'supervisor'] as $role) {
            $account = Account::factory()->{$role}()->create();
            $originalPassword = $account->password;

            $this->actingAs($manager)->get(route('accounts.edit', $account))->assertForbidden();

            $this->actingAs($manager)->put(route('accounts.update', $account), [
                'fullname' => 'Diambil Alih',
                'username' => $account->username,
                'password' => 'passwordbaru',
                'division_id' => $division->id,
            ])->assertForbidden();

            $account->refresh();
            $this->assertNotSame('Diambil Alih', $account->fullname, "Manager changed a {$role} account.");
            $this->assertSame($originalPassword, $account->password, "Manager reset the password of a {$role} account.");
        }
    }

    public function test_manager_only_sees_edit_and_delete_buttons_for_staff(): void
    {
        $manager = Account::factory()->manager()->create();
        $employee = Account::factory()->employee()->create();
        $admin = Account::factory()->admin()->create();

        $this->actingAs($manager)->get(route('accounts.index'))
            ->assertSee(route('accounts.edit', $employee))
            ->assertSee('action="'.route('accounts.destroy', $employee).'"', false)
            ->assertDontSee(route('accounts.edit', $admin))
            ->assertDontSee('action="'.route('accounts.destroy', $admin).'"', false);
    }

    public function test_admin_can_update_any_account_including_its_role(): void
    {
        $admin = Account::factory()->admin()->create();
        $manager = Account::factory()->manager()->create();
        $supervisorRole = Role::where('code_role', Role::SUPERVISOR)->firstOrFail();

        $this->actingAs($admin)->put(route('accounts.update', $manager), [
            'fullname' => $manager->fullname,
            'username' => $manager->username,
            'role_id' => $supervisorRole->id,
            'division_id' => $manager->division_id,
        ])->assertRedirect(route('accounts.index'));

        $this->assertSame(Role::SUPERVISOR, $manager->refresh()->role->code_role);
    }

    public function test_sole_active_admin_cannot_remove_its_admin_role(): void
    {
        $admin = Account::factory()->admin()->create();
        Account::factory()->admin()->inactive()->create();
        $employeeRole = Role::where('code_role', Role::EMPLOYEE)->firstOrFail();

        $this->actingAs($admin)->put(route('accounts.update', $admin), [
            'fullname' => $admin->fullname,
            'username' => $admin->username,
            'role_id' => $employeeRole->id,
            'division_id' => Division::factory()->create()->id,
        ])->assertSessionHasErrors(['role_id' => 'Role tidak dapat diubah karena akun ini satu-satunya Admin aktif.']);

        $this->assertTrue($admin->refresh()->isAdmin());
    }

    public function test_admin_role_can_be_removed_while_another_active_admin_remains(): void
    {
        $admin = Account::factory()->admin()->create();
        $otherAdmin = Account::factory()->admin()->create();
        $employeeRole = Role::where('code_role', Role::EMPLOYEE)->firstOrFail();

        $this->actingAs($admin)->put(route('accounts.update', $otherAdmin), [
            'fullname' => $otherAdmin->fullname,
            'username' => $otherAdmin->username,
            'role_id' => $employeeRole->id,
            'division_id' => Division::factory()->create()->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(Role::EMPLOYEE, $otherAdmin->refresh()->role->code_role);
    }

    public function test_admin_accounts_need_no_division_but_other_roles_do(): void
    {
        $admin = Account::factory()->admin()->withoutDivision()->create();
        $adminRole = Role::where('code_role', Role::ADMIN)->firstOrFail();
        $employeeRole = Role::where('code_role', Role::EMPLOYEE)->firstOrFail();

        $this->actingAs($admin)->put(route('accounts.update', $admin), [
            'fullname' => 'Admin Baru',
            'username' => $admin->username,
            'role_id' => $adminRole->id,
            'division_id' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Admin Baru', $admin->refresh()->fullname);
        $this->assertNull($admin->division_id);

        $this->actingAs($admin)->post(route('accounts.store'), [
            'fullname' => 'Admin Kedua',
            'username' => 'adminkedua',
            'password' => 'password123',
            'role_id' => $adminRole->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('accounts', ['username' => 'adminkedua', 'division_id' => null]);

        $this->actingAs($admin)->post(route('accounts.store'), [
            'fullname' => 'Staff Tanpa Divisi',
            'username' => 'stafftanpadivisi',
            'password' => 'password123',
            'role_id' => $employeeRole->id,
        ])->assertSessionHasErrors(['division_id' => 'Divisi wajib diisi, kecuali untuk role Admin.']);
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
            ->assertSessionHasErrors(['fullname', 'username', 'division_id'])
            ->assertSessionDoesntHaveErrors('role_id');

        $this->actingAs(Account::factory()->admin()->create())
            ->put(route('accounts.update', $account), ['username' => 'ada spasi'])
            ->assertSessionHasErrors(['fullname', 'username', 'role_id', 'division_id']);
    }

    public function test_only_admin_edit_form_has_role_field(): void
    {
        $account = Account::factory()->employee()->create();

        $this->actingAs(Account::factory()->manager()->create())
            ->get(route('accounts.edit', $account))
            ->assertOk()
            ->assertDontSee('name="role_id"', false)
            ->assertSee('Role hanya dapat diubah oleh Admin.');

        $this->actingAs(Account::factory()->admin()->create())
            ->get(route('accounts.edit', $account))
            ->assertOk()
            ->assertSee('name="role_id"', false)
            ->assertDontSee('Role hanya dapat diubah oleh Admin.');
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

    public function test_manager_can_hard_delete_staff_but_not_other_roles(): void
    {
        $manager = Account::factory()->manager()->create();
        $employee = Account::factory()->employee()->create(['fullname' => 'Akun Dihapus']);
        $freelancer = Account::factory()->freelance()->create();

        $this->actingAs($manager)->delete(route('accounts.destroy', $employee))->assertRedirect(route('accounts.index'));
        $this->actingAs($manager)->delete(route('accounts.destroy', $freelancer))->assertRedirect(route('accounts.index'));

        $this->assertDatabaseMissing('accounts', ['id' => $employee->id]);
        $this->assertDatabaseMissing('accounts', ['id' => $freelancer->id]);
        $this->actingAs($manager)->get(route('accounts.index'))->assertDontSee('Akun Dihapus');

        foreach (['admin', 'manager', 'supervisor'] as $role) {
            $account = Account::factory()->{$role}()->create();

            $this->actingAs($manager)->delete(route('accounts.destroy', $account))->assertForbidden();
            $this->assertDatabaseHas('accounts', ['id' => $account->id]);
        }
    }

    public function test_admin_can_delete_another_admin_but_not_itself(): void
    {
        $admin = Account::factory()->admin()->create();
        $otherAdmin = Account::factory()->admin()->create();

        $this->actingAs($admin)->delete(route('accounts.destroy', $otherAdmin))->assertRedirect(route('accounts.index'));
        $this->actingAs($admin)->delete(route('accounts.destroy', $admin))->assertForbidden();

        $this->assertDatabaseMissing('accounts', ['id' => $otherAdmin->id]);
        $this->assertDatabaseHas('accounts', ['id' => $admin->id]);
    }

    public function test_account_with_task_history_is_not_deleted_and_shows_the_reason(): void
    {
        $manager = Account::factory()->manager()->create();
        $pic = Account::factory()->employee()->create();
        $commenter = Account::factory()->employee()->create();
        TaskAssignment::factory()->for(Task::factory())->for($pic, 'account')->create();
        TaskComment::factory()->for(Task::factory())->for($commenter, 'author')->create();

        foreach ([$pic, $commenter] as $account) {
            $this->actingAs($manager)
                ->from(route('accounts.index'))
                ->delete(route('accounts.destroy', $account))
                ->assertRedirect(route('accounts.index'))
                ->assertSessionHas('error', 'Akun ini tidak dapat dihapus karena masih memiliki riwayat tugas, komentar, atau agenda.');

            $this->assertDatabaseHas('accounts', ['id' => $account->id]);
        }

        $this->actingAs($manager)
            ->deleteJson(route('accounts.destroy', $pic))
            ->assertConflict();
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
