<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Division;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskAssignment;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskRoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Division $division;

    protected Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $this->division = Division::factory()->create();
        $this->task = Task::factory()->for($this->division)->create(['status' => Task::STATUS_IN_PROGRESS]);
    }

    public function test_the_five_roles_receive_the_permissions_of_the_rbac_matrix(): void
    {
        $this->assertSame(5, Role::count());

        foreach (PermissionSeeder::ROLE_PERMISSIONS as $roleCode => $codes) {
            $this->assertEqualsCanonicalizing(
                $codes,
                Role::where('code_role', $roleCode)->sole()->permissions()->pluck('code')->all(),
                "Role {$roleCode} has the wrong permissions.",
            );
        }
    }

    public function test_permission_seeder_can_run_twice_without_duplicates(): void
    {
        $this->seed(PermissionSeeder::class);

        $this->assertDatabaseCount('permissions', 6);
        $this->assertDatabaseCount('role_permissions', collect(PermissionSeeder::ROLE_PERMISSIONS)->flatten()->count());
    }

    public function test_every_role_can_view_every_task(): void
    {
        foreach (['admin', 'manager', 'supervisor', 'employee', 'freelance'] as $role) {
            $this->actingAs(Account::factory()->{$role}()->create())
                ->getJson(route('api.tasks.show', $this->task))
                ->assertOk();
        }
    }

    public function test_viewing_does_not_grant_changing_tasks_outside_the_users_authority(): void
    {
        $outsider = Account::factory()->manager()->create();

        $this->actingAs($outsider)
            ->patchJson(route('api.tasks.update', $this->task), ['title' => 'Diubah', 'version' => 1])
            ->assertForbidden();
        $this->actingAs($outsider)
            ->putJson(route('api.tasks.pics.update', $this->task), ['pic_ids' => [], 'version' => 1])
            ->assertForbidden();

        $this->assertSame(1, $this->task->fresh()->version);
    }

    public function test_supervisor_manages_tasks_of_their_division_like_a_manager(): void
    {
        $supervisor = Account::factory()->supervisor()->for($this->division)->create();
        $newPic = Account::factory()->employee()->for($this->division)->create();

        $this->actingAs($supervisor)
            ->patchJson(route('api.tasks.update', $this->task), ['title' => 'Diperbarui supervisor', 'version' => 1])
            ->assertOk();
        $this->actingAs($supervisor)
            ->putJson(route('api.tasks.pics.update', $this->task), ['pic_ids' => [$newPic->id], 'version' => 2])
            ->assertOk();

        $this->task->update(['status' => Task::STATUS_ON_REVIEW, 'requires_review' => true]);

        $this->actingAs($supervisor)
            ->postJson(route('api.tasks.reviews.store', $this->task), ['decision' => 'approved', 'version' => 3])
            ->assertOk()
            ->assertJsonPath('data.status', Task::STATUS_DONE);
    }

    public function test_supervisor_cannot_manage_tasks_of_another_division(): void
    {
        $supervisor = Account::factory()->supervisor()->create();

        $this->actingAs($supervisor)
            ->patchJson(route('api.tasks.update', $this->task), ['title' => 'Diubah', 'version' => 1])
            ->assertForbidden();
    }

    public function test_staff_and_freelance_cannot_forward_or_review(): void
    {
        foreach (['employee', 'freelance'] as $role) {
            $account = Account::factory()->{$role}()->for($this->division)->create();
            TaskAssignment::factory()->for($this->task)->for($account, 'account')->create();

            $this->actingAs($account)
                ->putJson(route('api.tasks.pics.update', $this->task), ['pic_ids' => [$account->id], 'version' => 1])
                ->assertForbidden();
            $this->actingAs($account)
                ->postJson(route('api.tasks.reviews.store', $this->task), ['decision' => 'approved', 'version' => 1])
                ->assertForbidden();
        }
    }

    public function test_deactivated_account_is_denied_everything(): void
    {
        $admin = Account::factory()->admin()->inactive()->create();

        $this->actingAs($admin)->getJson(route('api.tasks.index'))->assertForbidden();
        $this->actingAs($admin)->getJson(route('api.tasks.show', $this->task))->assertForbidden();
        $this->actingAs($admin)
            ->patchJson(route('api.tasks.update', $this->task), ['title' => 'Diubah', 'version' => 1])
            ->assertForbidden();
    }

    public function test_role_change_updates_access_and_keeps_the_history(): void
    {
        $account = Account::factory()->employee()->for($this->division)->create();
        TaskActivity::factory()->for($this->task)->for($account, 'account')->create();

        $this->actingAs($account)
            ->patchJson(route('api.tasks.update', $this->task), ['title' => 'Diubah', 'version' => 1])
            ->assertForbidden();

        $account->update(['role_id' => Role::where('code_role', Role::MANAGER)->value('id')]);

        $this->actingAs($account->fresh())
            ->patchJson(route('api.tasks.update', $this->task), ['title' => 'Diubah', 'version' => 1])
            ->assertOk();

        $this->assertDatabaseHas('task_activities', ['task_id' => $this->task->id, 'account_id' => $account->id, 'action' => 'status_changed']);
    }
}
