<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A shared agenda is seen by every account; an internal one only by Admin and the accounts of its target
     * divisions. Existing agendas were visible to everyone, so they become shared.
     */
    public function up(): void
    {
        Schema::table('calendar_events', function (Blueprint $table) {
            $table->boolean('is_shared')->default(false)->after('description');
            $table->index('start_at');
        });

        DB::table('calendar_events')->update(['is_shared' => true]);

        Schema::create('calendar_event_divisions', function (Blueprint $table) {
            $table->foreignId('calendar_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('division_id')->constrained()->cascadeOnDelete();
            $table->primary(['calendar_event_id', 'division_id']);
            $table->index('division_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_event_divisions');

        Schema::table('calendar_events', function (Blueprint $table) {
            $table->dropIndex(['start_at']);
            $table->dropColumn('is_shared');
        });
    }
};
