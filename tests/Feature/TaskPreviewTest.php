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

class TaskPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $this->travelTo('2026-11-05 10:00:00');
    }

    public function test_preview_contains_the_pop_up_fields_and_board_navigation(): void
    {
        $division = Division::factory()->create();
        $pic = Account::factory()->employee()->for($division)->create();
        $task = Task::factory()->for($division)->create([
            'title' => 'Konten Harbolnas',
            'start_date' => '2026-11-01',
            'deadline' => '2026-11-11',
            'status' => Task::STATUS_IN_PROGRESS,
        ]);
        TaskAssignment::factory()->for($task)->for($pic, 'account')->create();

        $this->actingAs(Account::factory()->freelance()->create())
            ->getJson(route('api.tasks.preview', $task))
            ->assertOk()
            ->assertJsonPath('data', [
                'id' => $task->id,
                'title' => 'Konten Harbolnas',
                'start_date' => '2026-11-01',
                'deadline' => '2026-11-11',
                'pics' => [['id' => $pic->id, 'fullname' => $pic->fullname]],
                'status' => Task::STATUS_IN_PROGRESS,
                'status_label' => 'On Process',
                'is_overdue' => false,
                'board' => [
                    'task_id' => $task->id,
                    'filters' => ['division_id' => $division->id, 'status' => Task::STATUS_IN_PROGRESS],
                ],
            ]);
    }

    public function test_preview_shows_empty_values_when_dates_or_pic_are_missing(): void
    {
        $task = Task::factory()->create(['start_date' => null, 'deadline' => null]);

        $this->actingAs(Account::factory()->employee()->create())
            ->getJson(route('api.tasks.preview', $task))
            ->assertOk()
            ->assertJsonPath('data.start_date', null)
            ->assertJsonPath('data.deadline', null)
            ->assertJsonPath('data.pics', []);
    }

    public function test_preview_uses_the_latest_task_data(): void
    {
        $task = Task::factory()->create(['title' => 'Lama']);
        $task->update(['title' => 'Terbaru']);

        $this->actingAs(Account::factory()->admin()->create())
            ->getJson(route('api.tasks.preview', $task))
            ->assertJsonPath('data.title', 'Terbaru');
    }

    public function test_deleted_or_missing_task_returns_a_clear_not_found_message(): void
    {
        $account = Account::factory()->employee()->create();
        $task = Task::factory()->create();
        $task->delete();

        foreach ([$task->id, 999] as $id) {
            $this->actingAs($account)
                ->getJson(route('api.tasks.preview', $id))
                ->assertNotFound()
                ->assertJsonPath('message', 'Tugas tidak ditemukan atau telah dihapus.');
        }
    }

    public function test_deactivated_account_and_guest_cannot_preview(): void
    {
        $task = Task::factory()->create();

        $this->actingAs(Account::factory()->employee()->inactive()->create())
            ->getJson(route('api.tasks.preview', $task))
            ->assertForbidden();

        auth()->logout();
        $this->getJson(route('api.tasks.preview', $task))->assertUnauthorized();
    }
}
