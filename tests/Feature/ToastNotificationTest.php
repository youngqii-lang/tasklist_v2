<?php

namespace Tests\Feature;

use App\Models\Account;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ToastNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_page_without_flash_messages_renders_empty_toast_container(): void
    {
        $this->actingAs(Account::factory()->manager()->create())
            ->get(route('divisions.index'))
            ->assertOk()
            ->assertSee('$store.toast.items', false)
            ->assertDontSee('$store.toast.success(', false)
            ->assertDontSee('$store.toast.error(', false);
    }

    public function test_successful_action_shows_success_toast(): void
    {
        $this->actingAs(Account::factory()->manager()->create())
            ->followingRedirects()
            ->post(route('divisions.store'), ['code_division' => 'fin', 'name' => 'Keuangan'])
            ->assertOk()
            ->assertSee('$store.toast.success(', false)
            ->assertSee('Divisi Keuangan berhasil dibuat.');
    }

    public function test_failed_action_with_one_invalid_field_shows_its_message_as_error_toast(): void
    {
        $this->followingRedirects()
            ->from(route('login'))
            ->post(route('login.store'), ['username' => 'tidak-ada', 'password' => 'salah'])
            ->assertOk()
            ->assertSee('$store.toast.error(', false)
            ->assertSee('Username atau password salah.');
    }

    public function test_failed_action_with_several_invalid_fields_shows_summary_error_toast(): void
    {
        $this->actingAs(Account::factory()->manager()->create())
            ->followingRedirects()
            ->from(route('divisions.create'))
            ->post(route('divisions.store'), ['code_division' => '', 'name' => ''])
            ->assertOk()
            ->assertSee('$store.toast.error(', false)
            ->assertSee('Gagal menyimpan. Terdapat 2 isian yang perlu diperbaiki.');
    }

    public function test_login_and_logout_show_success_toast(): void
    {
        Account::factory()->create(['username' => 'budi', 'fullname' => 'Budi Santoso']);

        $this->post(route('login.store'), ['username' => 'budi', 'password' => 'password'])
            ->assertSessionHas('status', 'Berhasil masuk. Selamat datang, Budi Santoso.');

        $this->post(route('logout'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Anda berhasil keluar.');
    }
}
