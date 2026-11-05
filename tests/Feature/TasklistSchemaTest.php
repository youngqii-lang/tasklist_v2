<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CalendarEvent;
use App\Models\Division;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskAssignment;
use App\Models\TaskComment;
use App\Models\TaskEvidence;
use App\Models\TaskReview;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TasklistSchemaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_seeders_create_all_roles_and_tasklist_permissions(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $this->assertEqualsCanonicalizing(
            [Role::ADMIN, Role::MANAGER, Role::SUPERVISOR, Role::EMPLOYEE, Role::FREELANCE],
            Role::pluck('code_role')->all(),
        );
        $this->assertEqualsCanonicalizing(
            ['task.view_all', 'task.create', 'task.update_all', 'task.update_assigned', 'task.assign', 'task.review'],
            Permission::pluck('code')->all(),
        );
    }

    public function test_roles_can_be_granted_permissions(): void
    {
        $role = Role::where('code_role', Role::SUPERVISOR)->firstOrFail();
        $permission = Permission::factory()->create();

        $role->permissions()->attach($permission);

        $this->assertTrue($permission->roles->contains($role));
    }

    public function test_new_task_uses_the_erd_defaults(): void
    {
        $task = Task::create([
            'title' => 'Posting konten promo',
            'division_id' => Division::factory()->create()->id,
        ])->refresh();

        $this->assertSame('backlog', $task->status);
        $this->assertSame('medium', $task->priority);
        $this->assertSame(1, $task->version);
        $this->assertFalse($task->requires_review);
        $this->assertFalse($task->evidence_required);
    }

    public function test_new_account_is_active_by_default(): void
    {
        $this->assertTrue(Account::factory()->create()->refresh()->is_active);
    }

    public function test_task_keeps_assignment_history_and_tracks_active_pics(): void
    {
        $task = Task::factory()->create();
        $currentPic = TaskAssignment::factory()->for($task)->create();
        TaskAssignment::factory()->for($task)->unassigned()->create();

        $this->assertCount(2, $task->assignments);
        $this->assertTrue($task->assignments()->active()->sole()->is($currentPic));
    }

    public function test_task_has_collaborator_divisions_separate_from_its_owner(): void
    {
        $task = Task::factory()->create();
        $collaborator = Division::factory()->create();
        $manager = Account::factory()->manager()->create();

        $task->collaboratorDivisions()->attach($collaborator, ['added_by' => $manager->id, 'created_at' => now()]);

        $this->assertTrue($task->collaboratorDivisions->sole()->is($collaborator));
        $this->assertSame($manager->id, $task->collaboratorDivisions->sole()->pivot->added_by);
        $this->assertFalse($task->collaboratorDivisions->contains($task->division));
    }

    public function test_comment_replies_belong_to_their_parent(): void
    {
        $comment = TaskComment::factory()->create();
        $reply = TaskComment::factory()->replyTo($comment)->create();

        $this->assertTrue($comment->replies->sole()->is($reply));
        $this->assertSame($comment->task_id, $reply->task_id);
    }

    public function test_task_activity_is_append_only_without_updated_at(): void
    {
        $activity = TaskActivity::factory()->create()->refresh();

        $this->assertNotNull($activity->created_at);
        $this->assertSame(['status' => ['old' => 'todo', 'new' => 'in_progress']], $activity->changes);
        $this->assertArrayNotHasKey('updated_at', $activity->getAttributes());
    }

    public function test_soft_deleting_a_task_keeps_its_history(): void
    {
        $task = Task::factory()->create();
        TaskActivity::factory()->for($task)->create();

        $task->delete();

        $this->assertSoftDeleted($task);
        $this->assertDatabaseHas('task_activities', ['task_id' => $task->id]);
    }

    public function test_force_deleting_a_task_removes_its_child_records(): void
    {
        $task = Task::factory()->create();
        TaskAssignment::factory()->for($task)->create();
        TaskEvidence::factory()->for($task)->create();
        TaskReview::factory()->for($task)->create();
        TaskComment::factory()->for($task)->create();
        TaskActivity::factory()->for($task)->create();
        $task->collaboratorDivisions()->attach(Division::factory()->create());

        $task->forceDelete();

        foreach (['task_assignments', 'task_evidences', 'task_reviews', 'task_comments', 'task_activities', 'task_divisions'] as $table) {
            $this->assertDatabaseMissing($table, ['task_id' => $task->id]);
        }
    }

    public function test_account_assigned_as_pic_cannot_be_deleted(): void
    {
        $assignment = TaskAssignment::factory()->create();

        $this->expectException(QueryException::class);

        $assignment->account->delete();
    }

    public function test_account_that_wrote_a_comment_cannot_be_deleted(): void
    {
        $comment = TaskComment::factory()->create();

        $this->expectException(QueryException::class);

        $comment->author->delete();
    }

    public function test_account_that_created_a_calendar_event_cannot_be_deleted(): void
    {
        $event = CalendarEvent::factory()->create();

        $this->expectException(QueryException::class);

        $event->creator->delete();
    }

    public function test_deleting_a_task_creator_keeps_the_task_without_creator(): void
    {
        $task = Task::factory()->create();

        $task->creator->delete();

        $this->assertNull($task->refresh()->creator_id);
    }

    public function test_division_that_owns_a_task_cannot_be_deleted(): void
    {
        $task = Task::factory()->create();

        $this->expectException(QueryException::class);

        $task->division->delete();
    }
}
