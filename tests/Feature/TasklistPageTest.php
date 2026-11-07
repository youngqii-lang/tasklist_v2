<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Division;
use App\Models\Task;
use App\Models\TaskAssignment;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TasklistPageTest extends TestCase
{
    use RefreshDatabase;

    protected Division $division;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $this->division = Division::factory()->create();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('tasks.index'))->assertRedirect(route('login'));
    }

    public function test_admin_sees_the_tasklist_with_create_button_and_every_division(): void
    {
        $admin = Account::factory()->admin()->withoutDivision()->create();
        $otherDivision = Division::factory()->create();

        $response = $this->actingAs($admin)->get(route('tasks.index'))->assertOk();

        $response->assertSee('openCreateForm()', false)
            ->assertSee('name="csrf-token"', false)
            ->assertViewHas('config', fn (array $config) => $config['can_create'] === true
                && $config['user']['is_admin'] === true
                && $config['divisions']->pluck('id')->sort()->values()->all() === collect([$this->division->id, $otherDivision->id])->sort()->values()->all()
                && collect($config['statuses'])->pluck('value')->all() === array_keys(Task::STATUS_LABELS));
    }

    public function test_manager_can_create_tasks(): void
    {
        $manager = Account::factory()->manager()->for($this->division)->create();

        $this->actingAs($manager)->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('openCreateForm()', false)
            ->assertViewHas('config', fn (array $config) => $config['can_create'] === true && $config['user']['is_admin'] === false);
    }

    public function test_staff_sees_the_tasklist_without_create_button(): void
    {
        $staff = Account::factory()->employee()->for($this->division)->create();

        $this->actingAs($staff)->get(route('tasks.index'))
            ->assertOk()
            ->assertDontSee('openCreateForm()', false)
            ->assertViewHas('config', fn (array $config) => $config['can_create'] === false);
    }

    public function test_pic_filter_lists_only_active_staff_and_freelancers_of_every_division(): void
    {
        $staff = Account::factory()->employee()->for($this->division)->create();
        $freelancer = Account::factory()->freelance()->for(Division::factory())->create();
        Account::factory()->employee()->inactive()->for($this->division)->create();
        $manager = Account::factory()->manager()->for($this->division)->create();

        $this->actingAs($staff)->get(route('tasks.index'))
            ->assertOk()
            ->assertViewHas('config', fn (array $config) => collect($config['pics'])->pluck('id')->sort()->values()->all()
                === collect([$staff->id, $freelancer->id])->sort()->values()->all()
                && ! collect($config['pics'])->contains('id', $manager->id));
    }

    public function test_inactive_account_cannot_open_the_tasklist(): void
    {
        $inactive = Account::factory()->employee()->inactive()->for($this->division)->create();

        $this->actingAs($inactive)->get(route('tasks.index'))->assertForbidden();
    }

    public function test_sidebar_links_to_the_tasklist(): void
    {
        $staff = Account::factory()->employee()->for($this->division)->create();

        $this->actingAs($staff)->get(route('dashboard'))->assertOk()->assertSee(route('tasks.index'));
    }

    public function test_task_abilities_tell_whether_the_user_manages_the_task(): void
    {
        $task = Task::factory()->for($this->division)->create();
        $manager = Account::factory()->manager()->for($this->division)->create();
        $otherManager = Account::factory()->manager()->for(Division::factory())->create();
        $pic = Account::factory()->employee()->for($this->division)->create();
        TaskAssignment::factory()->for($task)->for($pic, 'account')->create();

        $this->actingAs($manager)->getJson(route('api.tasks.show', $task))
            ->assertOk()
            ->assertJsonPath('data.abilities.manage', true);

        $this->actingAs($otherManager)->getJson(route('api.tasks.show', $task))
            ->assertOk()
            ->assertJsonPath('data.abilities.manage', false)
            ->assertJsonPath('data.abilities.update', false);

        $this->actingAs($pic)->getJson(route('api.tasks.show', $task))
            ->assertOk()
            ->assertJsonPath('data.abilities.manage', false)
            ->assertJsonPath('data.abilities.update', true);
    }
}
