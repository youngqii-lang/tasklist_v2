<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Store a display title for link evidence (TASK-18).
     */
    public function up(): void
    {
        Schema::table('task_evidences', function (Blueprint $table) {
            $table->string('title', 150)->nullable()->after('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_evidences', function (Blueprint $table) {
            $table->dropColumn('title');
        });
    }
};
