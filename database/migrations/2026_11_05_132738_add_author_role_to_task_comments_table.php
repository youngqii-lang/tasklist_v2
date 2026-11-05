<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store the role the author had when writing the comment (TASK-16), so later role changes do not rewrite the thread.
     */
    public function up(): void
    {
        Schema::table('task_comments', function (Blueprint $table) {
            $table->string('author_role', 50)->nullable()->after('author_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_comments', function (Blueprint $table) {
            $table->dropColumn('author_role');
        });
    }
};
