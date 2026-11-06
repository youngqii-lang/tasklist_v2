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
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TaskAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected Division $division;

    protected Task $task;

    protected Account $currentPic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $this->division = Division::factory()->create();
        $this->task = Task::factory()->for($this->division)->create(['status' => Task::STATUS_IN_PROGRESS]);
        $this->currentPic = Account::factory()->employee()->for($this->division)->create();
        TaskAssignment::factory()->for($this->task)->for($this->currentPic, 'account')->create();
    }

    /**
     * @param  list<int>  $picIds
     * @param  array<string, mixed>  $extra
     */
    protected function putPics(Account $account, array $picIds, array $extra = []): TestResponse
    {
        return $this->actingAs($account)->putJson(
            route('api.tasks.pics.update', $this->task),
            ['pic_ids' => $picIds, 'version' => $this->task->fresh()->version] + $extra,
        );
    }

    public function test_manager_can_forward_a_task_to_another_staff_of_their_division(): void
    {
        $manager = Account::factory()->manager()->for($this->division)->create();
        $newPic = Account::factory()->freelance()->for($this->division)->create();

        $this->putPics($manager, [$newPic->id], ['note' => 'Dialihkan karena cuti'])
            ->assertOk()
            ->assertJsonPath('message', 'PIC tugas berhasil diperbarui.')
            ->assertJsonCount(1, 'data.pics')
            ->assertJsonPath('data.pics.0.id', $newPic->id)
            ->assertJsonPath('data.status', Task::STATUS_IN_PROGRESS);

        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $this->task->id,
            'account_id' => $this->currentPic->id,
            'unassigned_by' => $manager->id,
            'note' => 'Dialihkan karena cuti',
        ]);
        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $this->task->id,
            'account_id' => $newPic->id,
            'assigned_by' => $manager->id,
            'unassigned_at' => null,
        ]);
    }

    public function test_previous_and_new_pic_are_recorded_in_the_history(): void
    {
        $manager = Account::factory()->manager()->for($this->division)->create();
        $newPic = Account::factory()->employee()->for($this->division)->create();

        $this->putPics($manager, [$newPic->id])->assertOk();

        $activity = TaskActivity::sole();
        $this->assertSame(TaskActivity::ACTION_PIC_CHANGED, $activity->action);
        $this->assertSame($manager->id, $activity->account_id);
        $this->assertSame([['id' => $this->currentPic->id, 'fullname' => $this->currentPic->fullname]], $activity->changes['pics']['from']);
        $this->assertSame([['id' => $newPic->id, 'fullname' => $newPic->fullname]], $activity->changes['pics']['to']);
        $this->assertSame(2, $this->task->fresh()->version);
    }

    public function test_additional_pic_can_be_added_while_keeping_the_current_one(): void
    {
        $manager = Account::factory()->manager()->for($this->division)->create();
        $secondPic = Account::factory()->employee()->for($this->division)->create();

        $this->putPics($manager, [$this->currentPic->id, $secondPic->id])
            ->assertOk()
            ->assertJsonCount(2, 'data.pics');

        $this->assertDatabaseCount('task_assignments', 2);
    }

    public function test_sending_the_same_pics_changes_nothing(): void
    {
        $manager = Account::factory()->manager()->for($this->division)->create();

        $this->putPics($manager, [$this->currentPic->id])->assertOk();

        $this->assertDatabaseCount('task_activities', 0);
        $this->assertSame(1, $this->task->fresh()->version);
    }

    public function test_admin_can_assign_staff_across_divisions(): void
    {
        $admin = Account::factory()->admin()->create();
        $otherDivisionStaff = Account::factory()->employee()->create();

        $this->putPics($admin, [$otherDivisionStaff->id])
            ->assertOk()
            ->assertJsonPath('data.pics.0.id', $otherDivisionStaff->id);
    }

    public function test_manager_cannot_assign_staff_outside_their_division(): void
    {
        $manager = Account::factory()->manager()->for($this->division)->create();
        $otherDivisionStaff = Account::factory()->employee()->create();

        $this->putPics($manager, [$otherDivisionStaff->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pic_ids.0' => 'PIC harus akun Staff aktif yang berada dalam kewenangan Anda.']);

        $this->assertTrue($this->task->hasActivePic($this->currentPic));
    }

    public function test_manager_cannot_reassign_tasks_of_another_division(): void
    {
        $manager = Account::factory()->manager()->create();

        $this->putPics($manager, [$this->currentPic->id])->assertForbidden();
    }

    public function test_staff_cannot_assign_or_forward_tasks(): void
    {
        $otherStaff = Account::factory()->employee()->for($this->division)->create();

        $this->putPics($this->currentPic, [$otherStaff->id])->assertForbidden();

        $this->assertTrue($this->task->hasActivePic($this->currentPic));
    }

    public function test_only_active_staff_can_be_assigned(): void
    {
        $admin = Account::factory()->admin()->create();
        $invalidPics = [
            Account::factory()->employee()->for($this->division)->inactive()->create(),
            Account::factory()->manager()->for($this->division)->create(),
        ];

        foreach ($invalidPics as $pic) {
            $this->putPics($admin, [$pic->id])->assertUnprocessable()->assertJsonValidationErrors('pic_ids.0');
        }
    }

    public function test_running_task_must_keep_at_least_one_pic(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->putPics($admin, [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pic_ids' => 'Tugas yang sedang berjalan wajib memiliki minimal satu PIC.']);
    }

    public function test_backlog_task_can_have_its_pics_removed(): void
    {
        $admin = Account::factory()->admin()->create();
        $this->task->update(['status' => Task::STATUS_BACKLOG]);

        $this->putPics($admin, [])->assertOk()->assertJsonPath('data.pics', []);
    }

    public function test_pic_cannot_be_changed_during_review_or_after_done(): void
    {
        $admin = Account::factory()->admin()->create();
        $newPic = Account::factory()->employee()->for($this->division)->create();

        foreach (Task::REVIEW_STAGE_STATUSES as $status) {
            $this->task->update(['status' => $status]);

            $this->putPics($admin, [$newPic->id])
                ->assertUnprocessable()
                ->assertJsonPath('message', 'PIC tidak dapat diganti saat tugas sedang diperiksa atau sudah selesai.');
        }

        $this->assertTrue($this->task->hasActivePic($this->currentPic));
        $this->assertFalse($this->task->hasActivePic($newPic));
    }

    public function test_stale_version_is_rejected(): void
    {
        $admin = Account::factory()->admin()->create();
        $newPic = Account::factory()->employee()->for($this->division)->create();

        $this->actingAs($admin)
            ->putJson(route('api.tasks.pics.update', $this->task), ['pic_ids' => [$newPic->id], 'version' => 99])
            ->assertConflict();

        $this->assertTrue($this->task->hasActivePic($this->currentPic));
    }
}
