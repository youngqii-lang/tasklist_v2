<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * System timestamps written while the application ran in UTC, per table.
     *
     * Calendar event start/end times are left alone: they are schedule times entered as wall-clock values, not
     * moments recorded by the system. Date-only columns (start_date, deadline) have no time and are not shifted.
     *
     * @var array<string, list<string>>
     */
    protected const array COLUMNS = [
        'roles' => ['created_at', 'updated_at'],
        'divisions' => ['created_at', 'updated_at'],
        'accounts' => ['created_at', 'updated_at'],
        'permissions' => ['created_at', 'updated_at'],
        'platforms' => ['created_at', 'updated_at'],
        'task_categories' => ['created_at', 'updated_at'],
        'tasks' => ['completed_at', 'created_at', 'updated_at', 'deleted_at'],
        'task_divisions' => ['created_at'],
        'task_assignments' => ['assigned_at', 'unassigned_at'],
        'task_evidences' => ['created_at', 'updated_at', 'deleted_at'],
        'task_reviews' => ['created_at', 'updated_at'],
        'task_comments' => ['created_at', 'updated_at', 'deleted_at'],
        'task_activities' => ['created_at'],
        'calendar_events' => ['created_at', 'updated_at', 'deleted_at'],
    ];

    /**
     * Move existing UTC timestamps to Asia/Jakarta (UTC+7), now that the application stores times in WIB.
     */
    public function up(): void
    {
        $this->shift(7);
    }

    /**
     * Move the timestamps back to UTC.
     */
    public function down(): void
    {
        $this->shift(-7);
    }

    protected function shift(int $hours): void
    {
        $driver = DB::connection()->getDriverName();

        foreach (self::COLUMNS as $table => $columns) {
            foreach ($columns as $column) {
                $expression = match ($driver) {
                    'mysql', 'mariadb' => "DATE_ADD(`{$column}`, INTERVAL {$hours} HOUR)",
                    'sqlite' => sprintf("datetime(\"%s\", '%+d hours')", $column, $hours),
                    default => null,
                };

                if ($expression === null) {
                    continue;
                }

                DB::table($table)->whereNotNull($column)->update([$column => DB::raw($expression)]);
            }
        }
    }
};
