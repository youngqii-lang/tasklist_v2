<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keep the actor's name with each history entry, so it still reads correctly after the account is gone.
     */
    public function up(): void
    {
        Schema::table('task_activities', function (Blueprint $table) {
            $table->string('actor_name_snapshot')->nullable()->after('account_id');
        });

        DB::table('task_activities')
            ->whereNotNull('account_id')
            ->update([
                'actor_name_snapshot' => DB::table('accounts')
                    ->whereColumn('accounts.id', 'task_activities.account_id')
                    ->select('fullname'),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_activities', function (Blueprint $table) {
            $table->dropColumn('actor_name_snapshot');
        });
    }
};
