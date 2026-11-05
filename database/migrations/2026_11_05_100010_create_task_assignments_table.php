<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('task_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->foreignId('unassigned_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamp('unassigned_at')->nullable();
            $table->text('note')->nullable();

            $table->index(['task_id', 'unassigned_at']);
            $table->index(['account_id', 'unassigned_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('task_assignments');
    }
};
