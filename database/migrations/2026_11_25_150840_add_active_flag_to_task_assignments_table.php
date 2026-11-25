<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An account may be an active PIC of a task only once. active_flag is 1 for an active assignment and null for a
     * released one, so the unique index ignores released rows and the PIC can be assigned again later.
     */
    public function up(): void
    {
        Schema::table('task_assignments', function (Blueprint $table) {
            $expression = 'CASE WHEN unassigned_at IS NULL THEN 1 END';
            $column = $table->unsignedTinyInteger('active_flag')->nullable();

            // SQLite cannot add a stored generated column to an existing table; a virtual one is indexed the same way.
            Schema::getConnection()->getDriverName() === 'sqlite'
                ? $column->virtualAs($expression)
                : $column->storedAs($expression);

            $table->unique(['task_id', 'account_id', 'active_flag'], 'task_assignments_active_pic_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_assignments', function (Blueprint $table) {
            $table->dropUnique('task_assignments_active_pic_unique');
            $table->dropColumn('active_flag');
        });
    }
};
