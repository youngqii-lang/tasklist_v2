<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Division;
use App\Models\Platform;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskCategory;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\DemoTaskSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_master_data_is_seeded_without_demo_data_outside_local(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertEqualsCanonicalizing(['SEO', 'MKT', 'SLS', 'CRT'], Division::pluck('code_division')->all());
        $this->assertSame(13, Platform::count());
        $this->assertDatabaseHas('platforms', ['slug' => 'jadiasn', 'name' => 'JADIASN']);
        $this->assertSame(7, TaskCategory::count());
        $this->assertDatabaseHas('task_categories', ['name' => 'Riset', 'requires_review' => false, 'evidence_required' => true]);

        $this->assertEqualsCanonicalizing(['admin', 'manager'], Account::pluck('username')->all());
        $this->assertSame('MKT', Account::firstWhere('username', 'manager')->division->code_division);
        $this->assertNull(Account::firstWhere('username', 'admin')->division_id);
        $this->assertSame(0, Task::count());
    }

    public function test_demo_data_is_seeded_locally(): void
    {
        $this->app['env'] = 'local';

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(14, Account::count());
        $this->assertSame(20, Task::count());
    }

    public function test_every_division_gets_a_supervisor_staff_and_freelancer(): void
    {
        $this->seed([DatabaseSeeder::class, DemoSeeder::class]);

        foreach (Division::all() as $division) {
            $this->assertEqualsCanonicalizing(
                [Role::SUPERVISOR, Role::EMPLOYEE, Role::FREELANCE],
                Account::where('division_id', $division->id)->whereNot('username', 'manager')->with('role')->get()->pluck('role.code_role')->all(),
            );
        }

        $this->assertSame(1, Account::whereRelation('role', 'code_role', Role::MANAGER)->count());
        $this->assertSame(1, Account::whereRelation('role', 'code_role', Role::ADMIN)->count());
    }

    public function test_demo_tasks_follow_the_workflow_rules(): void
    {
        $this->seed([DatabaseSeeder::class, DemoSeeder::class]);

        $this->assertSame(
            [
                Task::STATUS_BACKLOG => 3,
                Task::STATUS_TODO => 4,
                Task::STATUS_IN_PROGRESS => 5,
                Task::STATUS_READY_REVIEW => 3,
                Task::STATUS_ON_REVIEW => 2,
                Task::STATUS_DONE => 3,
            ],
            collect(array_keys(Task::STATUS_LABELS))->mapWithKeys(fn (string $status) => [$status => Task::where('status', $status)->count()])->all(),
        );

        $tasks = Task::with(['pics.role', 'evidences', 'reviews', 'activities', 'division'])->get();

        foreach ($tasks as $task) {
            $this->assertNotEmpty($task->activities, "{$task->title} has no history");
            $this->assertTrue($task->pics->every(fn (Account $pic) => $pic->division_id === $task->division_id && in_array($pic->role->code_role, Role::STAFF_ROLES, true)));

            if ($task->status === Task::STATUS_BACKLOG) {
                continue;
            }

            $this->assertCount(1, $task->pics, "{$task->title} has no PIC");
            $this->assertNotNull($task->deadline, "{$task->title} has no deadline");

            if (in_array($task->status, Task::REVIEW_STAGE_STATUSES, true)) {
                $this->assertTrue($task->requires_review);
                $this->assertNotEmpty($task->evidences);
            }

            $this->assertSame($task->status === Task::STATUS_DONE, $task->completed_at !== null);
            $this->assertSame($task->status === Task::STATUS_DONE ? 1 : 0, $task->reviews->count());
        }

        $this->assertGreaterThan(0, $tasks->filter->isOverdue()->count());
        $this->assertGreaterThan(0, $tasks->filter(fn (Task $task) => $task->deadline?->isToday())->count());
    }

    public function test_seeding_twice_does_not_duplicate_data(): void
    {
        $this->seed([DatabaseSeeder::class, DemoSeeder::class]);
        $this->seed([DatabaseSeeder::class, DemoSeeder::class]);

        $this->assertSame(4, Division::count());
        $this->assertSame(13, Platform::count());
        $this->assertSame(7, TaskCategory::count());
        $this->assertSame(14, Account::count());
        $this->assertSame(count(DemoTaskSeeder::TASKS), Task::count());
    }
}
