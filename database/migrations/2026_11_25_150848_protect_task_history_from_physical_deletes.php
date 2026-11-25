<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Foreign keys of the task history that physically deleted rows along with their task, parent comment, or account.
     * Tasks, comments, and evidence are soft deleted and accounts with task history are kept instead of deleted, so
     * these keys now RESTRICT: a physical delete that would take history with it fails instead of erasing it.
     *
     * Columns that only record who did something (assigned_by, deleted_by, submitter_id, ...) keep SET NULL.
     *
     * @var array<string, array<string, string>> table => [column => referenced table]
     */
    private const PROTECTED = [
        'task_activities' => ['task_id' => 'tasks'],
        'task_evidences' => ['task_id' => 'tasks'],
        'task_reviews' => ['task_id' => 'tasks'],
        'task_assignments' => ['task_id' => 'tasks', 'account_id' => 'accounts'],
        'task_comments' => ['task_id' => 'tasks', 'author_id' => 'accounts', 'parent_comment_id' => 'task_comments'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->replaceForeignKeys('restrict');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->replaceForeignKeys('cascade');
    }

    private function replaceForeignKeys(string $onDelete): void
    {
        foreach (self::PROTECTED as $tableName => $columns) {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                foreach (array_keys($columns) as $column) {
                    $table->dropForeign([$column]);
                }
            });

            Schema::table($tableName, function (Blueprint $table) use ($columns, $onDelete) {
                foreach ($columns as $column => $references) {
                    $table->foreign($column)->references('id')->on($references)->onDelete($onDelete);
                }
            });
        }
    }
};
