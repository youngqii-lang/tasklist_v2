<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit of every role an account was given. Names are copied in, so the audit still reads after an account is
     * deleted.
     */
    public function up(): void
    {
        Schema::create('account_role_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('account_fullname');
            $table->string('account_username');
            // null when the account was created with its first role.
            $table->foreignId('old_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('new_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('changed_by_fullname')->nullable();
            $table->string('changed_by_username')->nullable();
            $table->timestamp('changed_at');

            $table->index(['account_id', 'changed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_role_changes');
    }
};
