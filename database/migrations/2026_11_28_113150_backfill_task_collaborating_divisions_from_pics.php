<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The divisions of a task's active PICs now collaborate on it. Existing tasks get those divisions too, except their
     * owning division; collaborators that are already stored are kept.
     */
    public function up(): void
    {
        $rows = DB::table('task_assignments')
            ->join('accounts', 'accounts.id', '=', 'task_assignments.account_id')
            ->join('tasks', 'tasks.id', '=', 'task_assignments.task_id')
            ->whereNull('task_assignments.unassigned_at')
            ->whereNotNull('accounts.division_id')
            ->whereColumn('accounts.division_id', '<>', 'tasks.division_id')
            ->distinct()
            ->get(['task_assignments.task_id', 'accounts.division_id'])
            ->map(fn (object $row) => [
                'task_id' => $row->task_id,
                'division_id' => $row->division_id,
                'added_by' => null,
                'created_at' => now(),
            ])
            ->all();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('task_divisions')->insertOrIgnore($chunk);
        }
    }

    /**
     * The added rows cannot be told apart from collaborators chosen later, so they are left in place.
     */
    public function down(): void
    {
        //
    }
};
