<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Division;
use App\Models\Platform;
use App\Models\TaskCategory;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskOptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
    }

    public function test_admin_receives_every_division_and_the_master_data(): void
    {
        $admin = Account::factory()->admin()->withoutDivision()->create();
        Division::factory()->count(2)->create();
        $category = TaskCategory::factory()->create(['requires_review' => true]);
        Platform::factory()->create();

        $this->actingAs($admin)
            ->getJson(route('api.task-options.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data.divisions')
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonPath('data.categories.0.id', $category->id)
            ->assertJsonPath('data.categories.0.requires_review', true)
            ->assertJsonCount(1, 'data.platforms')
            ->assertJsonCount(4, 'data.priorities')
            ->assertJsonPath('data.statuses', [
                ['value' => 'backlog', 'label' => 'Back Log'],
                ['value' => 'todo', 'label' => 'To Do'],
            ]);
    }

    public function test_manager_only_receives_their_own_division(): void
    {
        $manager = Account::factory()->manager()->create();
        Division::factory()->create();

        $this->actingAs($manager)
            ->getJson(route('api.task-options.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data.divisions')
            ->assertJsonPath('data.divisions.0.id', $manager->division_id);
    }

    public function test_staff_cannot_load_the_task_form_options(): void
    {
        $employee = Account::factory()->employee()->create();

        $this->actingAs($employee)->getJson(route('api.task-options.index'))->assertForbidden();
        $this->actingAs($employee)
            ->getJson(route('api.task-options.pics', ['division_id' => $employee->division_id]))
            ->assertForbidden();
    }

    public function test_pic_options_only_list_active_staff_of_the_division(): void
    {
        $manager = Account::factory()->manager()->create();
        $division = $manager->division;
        $employee = Account::factory()->employee()->for($division)->create();
        $freelance = Account::factory()->freelance()->for($division)->create();
        Account::factory()->employee()->for($division)->inactive()->create();
        Account::factory()->supervisor()->for($division)->create();
        Account::factory()->employee()->create();

        $response = $this->actingAs($manager)
            ->getJson(route('api.task-options.pics', ['division_id' => $division->id]))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertEqualsCanonicalizing(
            [$employee->id, $freelance->id],
            collect($response->json('data'))->pluck('id')->all(),
        );
    }

    public function test_manager_cannot_list_pics_of_another_division(): void
    {
        $manager = Account::factory()->manager()->create();
        $otherDivision = Division::factory()->create();

        $this->actingAs($manager)
            ->getJson(route('api.task-options.pics', ['division_id' => $otherDivision->id]))
            ->assertForbidden();
    }

    public function test_manager_must_choose_a_division_for_pic_options(): void
    {
        $manager = Account::factory()->manager()->create();

        $this->actingAs($manager)
            ->getJson(route('api.task-options.pics'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['division_id' => 'Divisi wajib diisi.']);
    }

    public function test_admin_receives_staff_of_every_division_without_a_division_filter(): void
    {
        $admin = Account::factory()->admin()->create();
        Account::factory()->employee()->count(2)->create();

        $this->actingAs($admin)
            ->getJson(route('api.task-options.pics'))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
