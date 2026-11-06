<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Division;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskAssignment;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskActivityHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected Division $division;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $this->division = Division::factory()->create();
    }

    public function test_full_task_lifecycle_is_recorded_newest_first(): void
    {
        $manager = Account::factory()->manager()->for($this->division)->create();
        $pic = Account::factory()->employee()->for($this->division)->create();
        $secondPic = Account::factory()->employee()->for($this->division)->create();

        $taskId = $this->actingAs($manager)->postJson(route('api.tasks.store'), [
            'title' => 'Kampanye Desember',
            'division_id' => $this->division->id,
            'status' => Task::STATUS_TODO,
            'deadline' => now()->addWeek()->toDateString(),
            'pic_ids' => [$pic->id],
            'requires_review' => true,
        ])->assertCreated()->json('data.id');

        $steps = [
            [$manager, 'patchJson', 'api.tasks.update', ['title' => 'Kampanye Desember 2026']],
            [$manager, 'putJson', 'api.tasks.pics.update', ['pic_ids' => [$pic->id, $secondPic->id]]],
            [$pic, 'patchJson', 'api.tasks.status.update', ['status' => Task::STATUS_IN_PROGRESS]],
            [$pic, 'postJson', 'api.tasks.evidences.store', ['type' => 'link', 'url' => 'https://example.com/v1']],
            [$pic, 'patchJson', 'api.tasks.status.update', ['status' => Task::STATUS_READY_REVIEW]],
            [$manager, 'patchJson', 'api.tasks.status.update', ['status' => Task::STATUS_ON_REVIEW]],
            [$manager, 'postJson', 'api.tasks.reviews.store', ['decision' => 'revision_requested', 'note' => 'Ganti foto']],
            [$pic, 'patchJson', 'api.tasks.status.update', ['status' => Task::STATUS_READY_REVIEW]],
            [$manager, 'patchJson', 'api.tasks.status.update', ['status' => Task::STATUS_ON_REVIEW]],
            [$manager, 'postJson', 'api.tasks.reviews.store', ['decision' => 'approved']],
        ];

        foreach ($steps as [$account, $method, $route, $data]) {
            $this->actingAs($account)
                ->{$method}(route($route, $taskId), $data + ['version' => Task::find($taskId)->version])
                ->assertSuccessful();
        }

        $this->actingAs($pic)
            ->getJson(route('api.tasks.activities.index', $taskId))
            ->assertOk()
            ->assertJsonPath('data.*.action', [
                TaskActivity::ACTION_APPROVED,
                TaskActivity::ACTION_STATUS_CHANGED,
                TaskActivity::ACTION_RESUBMITTED,
                TaskActivity::ACTION_REVISION_REQUESTED,
                TaskActivity::ACTION_STATUS_CHANGED,
                TaskActivity::ACTION_SUBMITTED,
                TaskActivity::ACTION_EVIDENCE_ADDED,
                TaskActivity::ACTION_STATUS_CHANGED,
                TaskActivity::ACTION_PIC_CHANGED,
                TaskActivity::ACTION_UPDATED,
                TaskActivity::ACTION_CREATED,
            ])
            ->assertJsonPath('data.0.label', 'Tugas disetujui')
            ->assertJsonPath('data.0.account.id', $manager->id)
            ->assertJsonPath('data.10.changes.status.to', Task::STATUS_TODO)
            ->assertJsonPath('data.10.changes.pics.to.0.id', $pic->id);
    }

    public function test_each_activity_shows_who_when_and_what_changed(): void
    {
        $admin = Account::factory()->admin()->create();
        $task = Task::factory()->for($this->division)->create(['title' => 'Lama']);

        $this->actingAs($admin)
            ->patchJson(route('api.tasks.update', $task), ['title' => 'Baru', 'version' => 1])
            ->assertOk();

        $this->actingAs($admin)
            ->getJson(route('api.tasks.activities.index', $task))
            ->assertOk()
            ->assertJsonPath('data.0.action', TaskActivity::ACTION_UPDATED)
            ->assertJsonPath('data.0.label', 'Informasi tugas diperbarui')
            ->assertJsonPath('data.0.account.fullname', $admin->fullname)
            ->assertJsonPath('data.0.changes.title', ['from' => 'Lama', 'to' => 'Baru'])
            ->assertJsonStructure(['data' => [['created_at']]]);
    }

    public function test_task_without_activity_returns_an_empty_list(): void
    {
        $admin = Account::factory()->admin()->create();
        $task = Task::factory()->create();

        $this->actingAs($admin)->getJson(route('api.tasks.activities.index', $task))->assertOk()->assertJsonPath('data', []);
    }

    public function test_history_is_visible_to_every_active_account(): void
    {
        $task = Task::factory()->for($this->division)->create();

        foreach (['admin', 'manager', 'supervisor', 'employee', 'freelance'] as $role) {
            $this->actingAs(Account::factory()->{$role}()->create())
                ->getJson(route('api.tasks.activities.index', $task))
                ->assertOk();
        }

        $this->actingAs(Account::factory()->employee()->inactive()->create())
            ->getJson(route('api.tasks.activities.index', $task))
            ->assertForbidden();
    }

    public function test_history_cannot_be_changed_through_the_api(): void
    {
        $admin = Account::factory()->admin()->create();
        $activity = TaskActivity::factory()->create();

        $this->actingAs($admin)->postJson(route('api.tasks.activities.index', $activity->task_id), ['action' => 'created'])->assertMethodNotAllowed();
        $this->actingAs($admin)->deleteJson(route('api.tasks.activities.index', $activity->task_id))->assertMethodNotAllowed();
    }

    public function test_history_survives_a_change_of_pic(): void
    {
        $admin = Account::factory()->admin()->create();
        $formerPic = Account::factory()->employee()->for($this->division)->create();
        $newPic = Account::factory()->employee()->for($this->division)->create();
        $task = Task::factory()->for($this->division)->create(['status' => Task::STATUS_IN_PROGRESS]);
        TaskAssignment::factory()->for($task)->for($formerPic, 'account')->create();
        TaskActivity::factory()->for($task)->for($formerPic, 'account')->create();

        $this->actingAs($admin)
            ->putJson(route('api.tasks.pics.update', $task), ['pic_ids' => [$newPic->id], 'version' => 1])
            ->assertOk();

        $this->actingAs($newPic)
            ->getJson(route('api.tasks.activities.index', $task))
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.account.id', $formerPic->id);
    }
}
