<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The author's role is read from the account instead of being copied into the comment. A comment carries the
     * client_request_id of the submission that created it, so a retried submission returns the same comment.
     */
    public function up(): void
    {
        Schema::table('task_comments', function (Blueprint $table) {
            $table->dropColumn('author_role');
        });

        Schema::table('task_comments', function (Blueprint $table) {
            $table->uuid('client_request_id')->nullable()->after('content');
            $table->unique(['author_id', 'client_request_id']);
            $table->index('parent_comment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_comments', function (Blueprint $table) {
            $table->dropIndex(['parent_comment_id']);
            $table->dropUnique(['author_id', 'client_request_id']);
            $table->dropColumn('client_request_id');
        });

        Schema::table('task_comments', function (Blueprint $table) {
            $table->string('author_role', 50)->nullable()->after('author_id');
        });
    }
};
