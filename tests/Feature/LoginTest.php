<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Division;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_shows_welcome_message(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee('Selamat datang');
        $response->assertSee('src="'.asset('images/logo.png').'"', false);
    }

    public function test_login_page_has_password_visibility_toggle(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-password-toggle="password"', false)
            ->assertSee('aria-label="Tampilkan password"', false);
    }

    public function test_guest_is_redirected_to_login_from_dashboard(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_account_can_login_with_valid_username_and_password(): void
    {
        $account = Account::factory()->create(['username' => 'budi']);

        $response = $this->post(route('login.store'), [
            'username' => 'budi',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($account);
    }

    public function test_account_cannot_login_with_wrong_password(): void
    {
        Account::factory()->create(['username' => 'budi']);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'username' => 'budi',
            'password' => 'salah',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_dashboard_greets_account_with_role_and_division(): void
    {
        $account = Account::factory()
            ->for(Role::factory()->state(['name' => 'Manager']))
            ->for(Division::factory()->state(['name' => 'Keuangan']))
            ->create(['fullname' => 'Budi Santoso']);

        $this->actingAs($account)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Halo, Budi Santoso!')
            ->assertSee('Manager')
            ->assertSee('Keuangan');
    }

    public function test_dashboard_renders_for_account_without_division(): void
    {
        $account = Account::factory()->withoutDivision()->create();

        $this->actingAs($account)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tanpa Divisi');
    }

    public function test_sidebar_shows_navigation_and_active_account(): void
    {
        $account = Account::factory()->create(['username' => 'budi']);

        $this->actingAs($account)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('href="'.route('dashboard').'"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('src="'.asset('images/logo.png').'"', false)
            ->assertSee('Akun Aktif')
            ->assertSee('@budi');
    }

    public function test_account_can_logout(): void
    {
        $account = Account::factory()->create();

        $this->actingAs($account)->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_seeded_default_manager_account_can_login(): void
    {
        $this->seed();

        $this->post(route('login.store'), [
            'username' => 'manager',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $manager = Account::where('username', 'manager')->first();

        $this->assertAuthenticatedAs($manager);
        $this->assertTrue($manager->isManager());
    }

    public function test_register_route_does_not_exist(): void
    {
        $this->get('/register')->assertNotFound();
    }
}
