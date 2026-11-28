<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A deadline gets a time of day, and every task requires QA review and evidence.
     *
     * Existing deadlines had no time, so they end at 23:59 of their date, as they did before. Tasks that are not Done
     * yet now require review and evidence; Done tasks keep the record of how they were finished.
     */
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->time('deadline_time')->nullable()->after('deadline');
        });

        DB::table('tasks')->whereNotNull('deadline')->update(['deadline_time' => '23:59:00']);

        DB::table('tasks')->where('status', '<>', 'done')->update([
            'requires_review' => true,
            'evidence_required' => true,
        ]);
    }

    /**
     * Drop the deadline time. The review and evidence requirements are left as they are, since which tasks had them
     * before cannot be told apart afterwards.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('deadline_time');
        });
    }
};
