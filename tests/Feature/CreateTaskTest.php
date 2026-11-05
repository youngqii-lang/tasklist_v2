<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Division;
use App\Models\Platform;
use App\Models\Task;
use App\Models\TaskCategory;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateTaskTest extends TestCase
{
    use RefreshDatabase;

    protected Division $division;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $this->division = Division::factory()->create();
    }

    /**
     * Build a valid request payload for the test division.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Posting konten promo akhir bulan',
            'division_id' => $this->division->id,
        ], $overrides);
    }

    public function test_admin_can_create_a_task_for_any_division(): void
    {
        $admin = Account::factory()->admin()->withoutDivision()->create();

        $response = $this->actingAs($admin)->postJson(route('api.tasks.store'), $this->payload());

        $response->assertCreated()
            ->assertJsonPath('message', 'Tugas berhasil dibuat.')
            ->assertJsonPath('data.title', 'Posting konten promo akhir bulan')
            ->assertJsonPath('data.division.id', $this->division->id)
            ->assertJsonPath('data.creator.id', $admin->id);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Posting konten promo akhir bulan',
            'division_id' => $this->division->id,
            'creator_id' => $admin->id,
        ]);
    }

    public function test_manager_can_create_a_task_for_their_own_division(): void
    {
        $manager = Account::factory()->manager()->for($this->division)->create();

        $this->actingAs($manager)
            ->postJson(route('api.tasks.store'), $this->payload())
            ->assertCreated();

        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_manager_cannot_create_a_task_for_another_division(): void
    {
        $manager = Account::factory()->manager()->create();

        $this->actingAs($manager)
            ->postJson(route('api.tasks.store'), $this->payload())
            ->assertForbidden();

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_manager_without_division_cannot_create_a_task(): void
    {
        $manager = Account::factory()->manager()->withoutDivision()->create();

        $this->actingAs($manager)
            ->postJson(route('api.tasks.store'), $this->payload())
            ->assertForbidden();
    }

    public function test_staff_and_freelance_cannot_create_a_task(): void
    {
        $accounts = [
            Account::factory()->employee()->for($this->division)->create(),
            Account::factory()->freelance()->for($this->division)->create(),
        ];

        foreach ($accounts as $account) {
            $this->actingAs($account)
                ->postJson(route('api.tasks.store'), $this->payload())
                ->assertForbidden();
        }

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_supervisor_can_create_a_task_for_their_own_division_only(): void
    {
        $supervisor = Account::factory()->supervisor()->for($this->division)->create();

        $this->actingAs($supervisor)->postJson(route('api.tasks.store'), $this->payload())->assertCreated();
        $this->actingAs($supervisor)
            ->postJson(route('api.tasks.store'), $this->payload(['division_id' => Division::factory()->create()->id]))
            ->assertForbidden();

        $this->assertDatabaseCount('tasks', 1);
    }

    public function test_guest_cannot_create_a_task(): void
    {
        $this->postJson(route('api.tasks.store'), $this->payload())->assertUnauthorized();
    }

    public function test_title_and_division_are_required(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('api.tasks.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'title' => 'Judul tugas wajib diisi.',
                'division_id' => 'Divisi wajib diisi.',
            ]);
    }

    public function test_division_category_and_platform_must_exist(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('api.tasks.store'), $this->payload([
                'division_id' => 999,
                'category_id' => 999,
                'platform_id' => 999,
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['division_id', 'category_id', 'platform_id']);
    }

    public function test_new_task_defaults_to_backlog_with_medium_priority(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('api.tasks.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.status', Task::STATUS_BACKLOG)
            ->assertJsonPath('data.priority', Task::PRIORITY_MEDIUM)
            ->assertJsonPath('data.pics', []);
    }

    public function test_priority_must_be_low_medium_high_or_urgent(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('api.tasks.store'), $this->payload(['priority' => 'critical']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('priority');

        $this->actingAs($admin)
            ->postJson(route('api.tasks.store'), $this->payload(['priority' => Task::PRIORITY_URGENT]))
            ->assertCreated()
            ->assertJsonPath('data.priority', Task::PRIORITY_URGENT);
    }

    public function test_new_task_can_only_start_in_backlog_or_todo(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('api.tasks.store'), $this->payload(['status' => Task::STATUS_DONE]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_todo_task_requires_a_pic_and_a_deadline(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('api.tasks.store'), $this->payload(['status' => Task::STATUS_TODO]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'pic_ids' => 'PIC wajib dipilih untuk tugas berstatus To Do.',
                'deadline' => 'Deadline wajib diisi untuk tugas berstatus To Do.',
            ]);

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_todo_task_is_saved_with_its_pics_assigned_by_the_creator(): void
    {
        $admin = Account::factory()->admin()->create();
        $employee = Account::factory()->employee()->for($this->division)->create();
        $freelance = Account::factory()->freelance()->for($this->division)->create();

        $response = $this->actingAs($admin)->postJson(route('api.tasks.store'), $this->payload([
            'status' => Task::STATUS_TODO,
            'deadline' => now()->addWeek()->toDateString(),
            'pic_ids' => [$employee->id, $freelance->id],
        ]));

        $response->assertCreated()
            ->assertJsonPath('data.status', Task::STATUS_TODO)
            ->assertJsonCount(2, 'data.pics');

        $task = Task::sole();

        foreach ([$employee, $freelance] as $pic) {
            $this->assertDatabaseHas('task_assignments', [
                'task_id' => $task->id,
                'account_id' => $pic->id,
                'assigned_by' => $admin->id,
                'unassigned_at' => null,
            ]);
        }
    }

    public function test_pic_must_be_active_staff_from_the_target_division(): void
    {
        $manager = Account::factory()->manager()->for($this->division)->create();
        $invalidPics = [
            Account::factory()->employee()->create(),
            Account::factory()->employee()->for($this->division)->inactive()->create(),
            Account::factory()->manager()->for($this->division)->create(),
            Account::factory()->supervisor()->for($this->division)->create(),
        ];

        foreach ($invalidPics as $pic) {
            $this->actingAs($manager)
                ->postJson(route('api.tasks.store'), $this->payload(['pic_ids' => [$pic->id]]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['pic_ids.0' => 'PIC harus akun Staff aktif dari divisi yang dipilih.']);
        }

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_admin_can_assign_active_staff_from_another_division(): void
    {
        $admin = Account::factory()->admin()->create();
        $otherDivisionStaff = Account::factory()->employee()->create();

        $this->actingAs($admin)
            ->postJson(route('api.tasks.store'), $this->payload(['pic_ids' => [$otherDivisionStaff->id]]))
            ->assertCreated()
            ->assertJsonPath('data.pics.0.id', $otherDivisionStaff->id);
    }

    public function test_the_same_pic_cannot_be_chosen_twice(): void
    {
        $admin = Account::factory()->admin()->create();
        $employee = Account::factory()->employee()->for($this->division)->create();

        $this->actingAs($admin)
            ->postJson(route('api.tasks.store'), $this->payload(['pic_ids' => [$employee->id, $employee->id]]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pic_ids.0');
    }

    public function test_deadline_cannot_be_in_the_past_or_before_the_start_date(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('api.tasks.store'), $this->payload(['deadline' => now()->subDay()->toDateString()]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('deadline');

        $this->actingAs($admin)
            ->postJson(route('api.tasks.store'), $this->payload([
                'start_date' => now()->addDays(5)->toDateString(),
                'deadline' => now()->addDays(2)->toDateString(),
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('deadline');

        $this->assertDatabaseCount('tasks', 0);
    }

    public function test_qa_requirement_follows_the_task_category(): void
    {
        $admin = Account::factory()->admin()->create();
        $category = TaskCategory::factory()->create(['requires_review' => true, 'evidence_required' => true]);

        $this->actingAs($admin)
            ->postJson(route('api.tasks.store'), $this->payload([
                'category_id' => $category->id,
                'requires_review' => false,
            ]))
            ->assertCreated()
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.requires_review', true)
            ->assertJsonPath('data.evidence_required', true);
    }

    public function test_qa_requirement_uses_the_submitted_value_without_a_category(): void
    {
        $admin = Account::factory()->admin()->create();

        $this->actingAs($admin)
            ->postJson(route('api.tasks.store'), $this->payload(['requires_review' => true]))
            ->assertCreated()
            ->assertJsonPath('data.category', null)
            ->assertJsonPath('data.requires_review', true)
            ->assertJsonPath('data.evidence_required', false);
    }

    public function test_optional_fields_are_saved(): void
    {
        $admin = Account::factory()->admin()->create();
        $platform = Platform::factory()->create();

        $this->actingAs($admin)
            ->postJson(route('api.tasks.store'), $this->payload([
                'description' => 'Konten untuk Instagram dan TikTok.',
                'platform_id' => $platform->id,
                'start_date' => now()->toDateString(),
                'deadline' => now()->addDays(3)->toDateString(),
            ]))
            ->assertCreated()
            ->assertJsonPath('data.description', 'Konten untuk Instagram dan TikTok.')
            ->assertJsonPath('data.platform.id', $platform->id)
            ->assertJsonPath('data.start_date', now()->toDateString())
            ->assertJsonPath('data.deadline', now()->addDays(3)->toDateString());
    }

    public function test_creator_is_taken_from_the_signed_in_account_not_the_payload(): void
    {
        $admin = Account::factory()->admin()->create();
        $otherAccount = Account::factory()->manager()->create();

        $this->actingAs($admin)
            ->postJson(route('api.tasks.store'), $this->payload(['creator_id' => $otherAccount->id]))
            ->assertCreated();

        $this->assertSame($admin->id, Task::sole()->creator_id);
    }
}
