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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('description')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('task_categories')->nullOnDelete();
            $table->foreignId('division_id')->constrained()->restrictOnDelete();
            $table->foreignId('platform_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('accounts')->nullOnDelete();
            $table->string('priority', 10)->default('medium');
            $table->string('status', 20)->default('backlog');
            $table->date('start_date')->nullable();
            $table->date('deadline')->nullable();
            $table->boolean('requires_review')->default(false);
            $table->boolean('evidence_required')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('accounts')->nullOnDelete();
            $table->timestamps();

            $table->index(['division_id', 'status']);
            $table->index(['status', 'deadline']);
            $table->index('start_date');
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
