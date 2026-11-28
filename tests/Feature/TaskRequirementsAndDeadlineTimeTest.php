<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Models\Account;
use App\Models\Division;
use App\Models\Task;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaskRequirementsAndDeadlineTimeTest extends TestCase
{
    use RefreshDatabase;

    protected Division $division;

    protected Account $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $this->travelTo(Carbon::parse('2026-12-01 14:00:00'));

        $this->division = Division::factory()->create();
        $this->admin = Account::factory()->admin()->withoutDivision()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function taskPayload(array $overrides = []): array
    {
        return [
            'title' => 'Desain feed Instagram',
            'division_id' => $this->division->id,
            'deadline' => '2026-12-05',
            'deadline_time' => '17:30',
            ...$overrides,
        ];
    }

    public function test_every_new_task_requires_review_and_evidence_even_when_asked_otherwise(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson(route('api.tasks.store'), $this->taskPayload(['requires_review' => false, 'evidence_required' => false]))
            ->assertCreated()
            ->assertJsonPath('data.requires_review', true)
            ->assertJsonPath('data.evidence_required', true)
            ->assertJsonPath('data.deadline', '2026-12-05')
            ->assertJsonPath('data.deadline_time', '17:30');

        $task = Task::findOrFail($response->json('data.id'));
        $this->assertTrue($task->requires_review);
        $this->assertTrue($task->evidence_required);
    }

    public function test_an_edit_cannot_turn_review_off(): void
    {
        $task = Task::factory()->for($this->division)->create(['creator_id' => $this->admin->id]);

        $response = $this->actingAs($this->admin)
            ->patchJson(route('api.tasks.update', $task), ['version' => $task->version, 'title' => 'Judul baru', 'requires_review' => false])
            ->assertOk()
            ->assertJsonPath('data.requires_review', true);

        $this->assertNotContains('requires_review', $response->json('data.editable_fields'));
        $this->assertContains('deadline_time', $response->json('data.editable_fields'));
        $this->assertTrue($task->refresh()->requires_review);
    }

    public function test_deadline_time_is_required_with_a_deadline(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('api.tasks.store'), $this->taskPayload(['deadline_time' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['deadline_time' => 'Jam deadline wajib diisi bersama tanggal deadline.']);

        $this->actingAs($this->admin)
            ->postJson(route('api.tasks.store'), $this->taskPayload(['deadline' => null, 'deadline_time' => null]))
            ->assertCreated()
            ->assertJsonPath('data.deadline_time', null);
    }

    public function test_a_deadline_today_must_end_at_a_time_that_has_not_passed(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('api.tasks.store'), $this->taskPayload(['deadline' => '2026-12-01', 'deadline_time' => '10:00']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('deadline_time');

        $this->actingAs($this->admin)
            ->postJson(route('api.tasks.store'), $this->taskPayload(['deadline' => '2026-12-01', 'deadline_time' => '16:00']))
            ->assertCreated()
            ->assertJsonPath('data.is_overdue', false);
    }

    public function test_changing_the_deadline_time_is_validated_and_recorded(): void
    {
        $task = Task::factory()->for($this->division)->create([
            'creator_id' => $this->admin->id,
            'deadline' => '2026-12-01',
            'deadline_time' => '18:00',
        ]);

        $this->actingAs($this->admin)
            ->patchJson(route('api.tasks.update', $task), ['version' => $task->version, 'deadline_time' => '09:00'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('deadline_time');

        $this->actingAs($this->admin)
            ->patchJson(route('api.tasks.update', $task), ['version' => $task->version, 'deadline_time' => '20:15'])
            ->assertOk()
            ->assertJsonPath('data.deadline_time', '20:15')
            ->assertJsonPath('data.history.0.description', 'Jam deadline: 18:00 → 20:15');
    }

    public function test_a_task_is_overdue_once_its_deadline_time_has_passed(): void
    {
        Task::factory()->for($this->division)->create(['status' => TaskStatus::Todo, 'deadline' => '2026-12-01', 'deadline_time' => '10:00']);
        Task::factory()->for($this->division)->create(['status' => TaskStatus::Todo, 'deadline' => '2026-12-01', 'deadline_time' => '17:00']);
        Task::factory()->for($this->division)->create(['status' => TaskStatus::Todo, 'deadline' => '2026-11-30', 'deadline_time' => '23:59']);
        Task::factory()->for($this->division)->create(['status' => TaskStatus::Done, 'deadline' => '2026-12-01', 'deadline_time' => '09:00']);

        $this->actingAs($this->admin)
            ->getJson(route('api.tasks.monitoring', ['scope' => 'tasklist']))
            ->assertOk()
            ->assertJsonPath('data.metrics.overdue', 2)
            ->assertJsonPath('data.metrics.due_today', 1);

        $overdue = collect($this->actingAs($this->admin)->getJson(route('api.tasks.index'))->assertOk()->json('data'))
            ->mapWithKeys(fn (array $task) => [$task['deadline_time'].' '.$task['status'] => $task['is_overdue']])
            ->sortKeys()
            ->all();

        $this->assertSame(['09:00 done' => false, '10:00 todo' => true, '17:00 todo' => false, '23:59 todo' => true], $overdue);
    }

    public function test_the_divisions_of_the_pics_become_collaborating_divisions_next_to_the_chosen_ones(): void
    {
        $creative = Division::factory()->create(['name' => 'Creative']);
        $sales = Division::factory()->create(['name' => 'Sales']);
        $seo = Division::factory()->create(['name' => 'SEO']);
        $creativeStaff = Account::factory()->employee()->for($creative)->create();
        $ownStaff = Account::factory()->employee()->for($this->division)->create();

        $response = $this->actingAs($this->admin)
            ->postJson(route('api.tasks.store'), $this->taskPayload([
                'pic_ids' => [$creativeStaff->id, $ownStaff->id],
                'collaborating_division_ids' => [$sales->id, $this->division->id],
            ]))
            ->assertCreated()
            ->assertJsonPath('data.collaborating_divisions.*.name', ['Creative', 'Sales']);

        $taskId = $response->json('data.id');

        $this->actingAs($this->admin)
            ->getJson(route('api.tasks.index', ['division' => $creative->code_division]))
            ->assertOk()
            ->assertJsonPath('data.*.id', [$taskId]);

        $this->actingAs($this->admin)
            ->getJson(route('api.tasks.index', ['division' => $seo->code_division]))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $seoStaff = Account::factory()->employee()->for($seo)->create();
        $task = Task::findOrFail($taskId);

        $this->actingAs($this->admin)
            ->patchJson(route('api.tasks.assign', $task), ['version' => $task->version, 'pic_ids' => [$creativeStaff->id, $ownStaff->id, $seoStaff->id]])
            ->assertOk()
            ->assertJsonPath('data.collaborating_divisions.*.name', ['Creative', 'SEO', 'Sales']);
    }

    public function test_an_edit_can_remove_a_chosen_collaborator_but_keeps_the_divisions_of_the_pics(): void
    {
        $creative = Division::factory()->create(['name' => 'Creative']);
        $sales = Division::factory()->create(['name' => 'Sales']);
        $creativeStaff = Account::factory()->employee()->for($creative)->create();

        $taskId = $this->actingAs($this->admin)
            ->postJson(route('api.tasks.store'), $this->taskPayload([
                'pic_ids' => [$creativeStaff->id],
                'collaborating_division_ids' => [$sales->id],
            ]))
            ->assertCreated()
            ->json('data.id');
        $task = Task::findOrFail($taskId);

        $this->actingAs($this->admin)
            ->patchJson(route('api.tasks.update', $task), ['version' => $task->version, 'collaborating_division_ids' => []])
            ->assertOk()
            ->assertJsonPath('data.collaborating_divisions.*.name', ['Creative'])
            ->assertJsonPath('data.history.0.description', 'Divisi kolaborator: Creative, Sales → Creative');
    }
}
