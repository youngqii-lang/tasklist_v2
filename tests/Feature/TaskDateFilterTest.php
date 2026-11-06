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

class TaskDateFilterTest extends TestCase
{
    use RefreshDatabase;

    protected Account $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PermissionSeeder::class]);

        $this->admin = Account::factory()->admin()->create();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return list<int>
     */
    protected function listedIds(array $query): array
    {
        return collect($this->actingAs($this->admin)->getJson(route('api.tasks.index', $query))->assertOk()->json('data'))
            ->pluck('id')
            ->sort()
            ->values()
            ->all();
    }

    public function test_filter_by_a_specific_deadline_date(): void
    {
        $match = Task::factory()->create(['deadline' => '2026-11-10']);
        Task::factory()->create(['deadline' => '2026-11-11']);
        Task::factory()->create(['deadline' => null]);

        $this->assertSame([$match->id], $this->listedIds(['date' => '2026-11-10']));
    }

    public function test_filter_by_month_and_year(): void
    {
        $november = Task::factory()->create(['deadline' => '2026-11-30']);
        $december = Task::factory()->create(['deadline' => '2026-12-01']);
        $nextYear = Task::factory()->create(['deadline' => '2027-01-15']);

        $this->assertSame([$november->id], $this->listedIds(['month' => '2026-11']));
        $this->assertSame([$november->id, $december->id], $this->listedIds(['year' => 2026]));
        $this->assertSame([$nextYear->id], $this->listedIds(['year' => 2027]));
    }

    public function test_filter_by_date_range_is_inclusive(): void
    {
        Task::factory()->create(['deadline' => '2026-11-04']);
        $first = Task::factory()->create(['deadline' => '2026-11-05']);
        $last = Task::factory()->create(['deadline' => '2026-11-09']);
        Task::factory()->create(['deadline' => '2026-11-10']);

        $this->assertSame([$first->id, $last->id], $this->listedIds(['date_from' => '2026-11-05', 'date_to' => '2026-11-09']));
    }

    public function test_period_can_use_the_start_date_instead_of_the_deadline(): void
    {
        $match = Task::factory()->create(['start_date' => '2026-11-03', 'deadline' => '2026-12-20']);
        Task::factory()->create(['start_date' => null, 'deadline' => '2026-11-03']);

        $this->assertSame([$match->id], $this->listedIds(['date_field' => 'start_date', 'month' => '2026-11']));
    }

    public function test_period_combines_with_division_pic_priority_and_status(): void
    {
        $division = Division::factory()->create();
        $pic = Account::factory()->employee()->for($division)->create();
        $match = Task::factory()->for($division)->create(['deadline' => '2026-11-10', 'priority' => 'high', 'status' => 'todo']);
        TaskAssignment::factory()->for($match)->for($pic, 'account')->create();
        $otherPic = Task::factory()->for($division)->create(['deadline' => '2026-11-10', 'priority' => 'high', 'status' => 'todo']);
        TaskAssignment::factory()->for($otherPic)->create();
        Task::factory()->for($division)->create(['deadline' => '2026-12-10', 'priority' => 'high', 'status' => 'todo']);

        $this->assertSame([$match->id], $this->listedIds([
            'month' => '2026-11',
            'division_id' => $division->id,
            'pic_id' => $pic->id,
            'priority' => 'high',
            'status' => 'todo',
        ]));
    }

    public function test_board_counts_and_summary_follow_the_period(): void
    {
        Task::factory()->count(2)->create(['deadline' => '2026-11-10', 'status' => 'todo']);
        Task::factory()->create(['deadline' => '2026-12-10', 'status' => 'todo']);

        $this->actingAs($this->admin)
            ->getJson(route('api.tasks.index', ['month' => '2026-11']))
            ->assertJsonPath('meta.status_counts.todo', 2);

        $this->actingAs($this->admin)
            ->getJson(route('api.tasks.summary', ['month' => '2026-11']))
            ->assertJsonPath('data.metrics.total', 2);
    }

    public function test_filtering_does_not_change_the_tasks(): void
    {
        $task = Task::factory()->create(['deadline' => '2026-11-10']);

        $this->listedIds(['date' => '2026-11-10']);

        $this->assertSame(1, $task->fresh()->version);
    }

    public function test_period_filters_are_validated(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('api.tasks.index', [
                'date' => '10-11-2026',
                'month' => '2026/11',
                'year' => 26,
                'date_field' => 'created_at',
                'date_from' => '2026-11-10',
                'date_to' => '2026-11-01',
            ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'date' => 'Tanggal harus berformat YYYY-MM-DD.',
                'month' => 'Bulan harus berformat YYYY-MM.',
                'year',
                'date_field',
                'date_to' => 'Tanggal akhir tidak boleh sebelum tanggal awal.',
            ]);
    }
}
