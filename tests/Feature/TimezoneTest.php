<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CalendarEvent;
use App\Models\Task;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TimezoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_runs_in_asia_jakarta(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', date_default_timezone_get());
        $this->assertSame('+07:00', now()->format('P'));
    }

    public function test_api_returns_times_with_the_indonesian_offset(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $task = Task::factory()->create();

        $this->actingAs(Account::factory()->admin()->create())
            ->getJson(route('api.tasks.show', $task))
            ->assertOk()
            ->assertJsonPath('data.created_at', fn (string $createdAt) => str_ends_with($createdAt, '+07:00'));
    }

    public function test_due_today_follows_the_indonesian_date(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class]);
        $this->travelTo('2026-11-06 01:00:00');
        Task::factory()->create(['deadline' => '2026-11-06', 'status' => Task::STATUS_TODO]);

        $this->actingAs(Account::factory()->admin()->create())
            ->getJson(route('api.tasks.summary'))
            ->assertJsonPath('data.metrics.due_today', 1)
            ->assertJsonPath('data.metrics.overdue', 0);
    }

    public function test_migration_shifts_existing_utc_timestamps_by_seven_hours_and_back(): void
    {
        $task = Task::factory()->create();
        $event = CalendarEvent::factory()->create(['start_at' => '2026-11-10 09:00:00']);
        DB::table('tasks')->where('id', $task->id)->update(['created_at' => '2026-11-05 20:00:00', 'completed_at' => null]);
        DB::table('calendar_events')->where('id', $event->id)->update(['created_at' => '2026-11-05 01:30:00']);

        $migration = require database_path('migrations/2026_11_06_153846_shift_existing_timestamps_to_asia_jakarta.php');
        $migration->up();

        $this->assertSame('2026-11-06 03:00:00', DB::table('tasks')->where('id', $task->id)->value('created_at'));
        $this->assertNull(DB::table('tasks')->where('id', $task->id)->value('completed_at'));
        $this->assertSame('2026-11-05 08:30:00', DB::table('calendar_events')->where('id', $event->id)->value('created_at'));
        $this->assertSame('2026-11-10 09:00:00', DB::table('calendar_events')->where('id', $event->id)->value('start_at'));

        $migration->down();

        $this->assertSame('2026-11-05 20:00:00', DB::table('tasks')->where('id', $task->id)->value('created_at'));
    }
}
