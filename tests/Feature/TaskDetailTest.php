<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Division;
use App\Models\Platform;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskAssignment;
use App\Models\TaskCategory;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskDetailTest extends TestCase
{
    use RefreshDatabase;

    protected Division $division;

    protected Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->division = Division::factory()->create();
        $this->task = Task::factory()->for($this->division)->create();
    }

    public function test_detail_contains_every_task_field(): void
    {
        $admin = Account::factory()->admin()->create();
        $category = TaskCategory::factory()->create();
        $platform = Platform::factory()->create();
        $pic = Account::factory()->employee()->for($this->division)->create();
        $this->task->update([
            'category_id' => $category->id,
            'platform_id' => $platform->id,
            'requires_review' => true,
        ]);
        TaskAssignment::factory()->for($this->task)->for($pic, 'account')->create();

        $this->actingAs($admin)
            ->getJson(route('api.tasks.show', $this->task))
            ->assertOk()
            ->assertJsonPath('data.id', $this->task->id)
            ->assertJsonPath('data.title', $this->task->title)
            ->assertJsonPath('data.description', $this->task->description)
            ->assertJsonPath('data.category.name', $category->name)
            ->assertJsonPath('data.division.id', $this->division->id)
            ->assertJsonPath('data.platform.name', $platform->name)
            ->assertJsonPath('data.pics.0.id', $pic->id)
            ->assertJsonPath('data.start_date', $this->task->start_date->toDateString())
            ->assertJsonPath('data.deadline', $this->task->deadline->toDateString())
            ->assertJsonPath('data.requires_review', true)
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.activities', [])
            ->assertJsonPath('data.abilities.update', true);
    }

    public function test_detail_shows_empty_values_for_missing_information(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->actingAs($admin)
            ->getJson(route('api.tasks.show', $this->task))
            ->assertOk()
            ->assertJsonPath('data.category', null)
            ->assertJsonPath('data.platform', null)
            ->assertJsonPath('data.pics', []);
    }

    public function test_detail_lists_the_newest_activity_first(): void
    {
        $admin = Account::factory()->admin()->create();
        $older = TaskActivity::factory()->for($this->task)->create(['created_at' => now()->subHour()]);
        $newer = TaskActivity::factory()->for($this->task)->create(['created_at' => now()]);

        $this->actingAs($admin)
            ->getJson(route('api.tasks.show', $this->task))
            ->assertJsonPath('data.activities.0.id', $newer->id)
            ->assertJsonPath('data.activities.1.id', $older->id);
    }

    public function test_manager_can_open_tasks_of_their_division_only(): void
    {
        $manager = Account::factory()->manager()->for($this->division)->create();
        $otherTask = Task::factory()->create();

        $this->actingAs($manager)->getJson(route('api.tasks.show', $this->task))->assertOk();
        $this->actingAs($manager)->getJson(route('api.tasks.show', $otherTask))->assertForbidden();
    }

    public function test_staff_can_only_open_tasks_they_are_pic_of(): void
    {
        $employee = Account::factory()->employee()->for($this->division)->create();
        $otherTask = Task::factory()->for($this->division)->create();
        TaskAssignment::factory()->for($this->task)->for($employee, 'account')->create();

        $this->actingAs($employee)
            ->getJson(route('api.tasks.show', $this->task))
            ->assertOk()
            ->assertJsonPath('data.abilities.update', false);

        $this->actingAs($employee)
            ->getJson(route('api.tasks.show', $otherTask))
            ->assertForbidden()
            ->assertJsonPath('message', 'Anda tidak memiliki akses untuk melakukan tindakan ini.');
    }

    public function test_missing_or_deleted_task_returns_not_found(): void
    {
        $admin = Account::factory()->admin()->create();
        $this->task->delete();

        foreach ([$this->task->id, 999] as $id) {
            $this->actingAs($admin)
                ->getJson(route('api.tasks.show', $id))
                ->assertNotFound()
                ->assertJsonPath('message', 'Tugas tidak ditemukan atau telah dihapus.');
        }
    }

    public function test_guest_cannot_open_a_task(): void
    {
        $this->getJson(route('api.tasks.show', $this->task))->assertUnauthorized();
    }
}
