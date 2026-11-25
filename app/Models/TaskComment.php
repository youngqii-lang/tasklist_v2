<?php

namespace App\Models;

use App\Exceptions\CommentReplyOutsideTaskException;
use Database\Factories\TaskCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A comment on a task, or a reply when parent_comment_id is set. A reply's parent must belong to the same task.
 * Comments never change the task's status and are not review decisions. deleted_at exists for a future, approved
 * delete-comment feature; nothing deletes comments yet.
 */
#[Fillable(['task_id', 'author_id', 'parent_comment_id', 'content', 'client_request_id'])]
class TaskComment extends Model
{
    /** @use HasFactory<TaskCommentFactory> */
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::saving(function (TaskComment $comment) {
            if ($comment->parent_comment_id === null || ! $comment->isDirty(['parent_comment_id', 'task_id'])) {
                return;
            }

            $parentTaskId = self::withTrashed()->whereKey($comment->parent_comment_id)->value('task_id');

            if ($parentTaskId === null || (int) $parentTaskId !== (int) $comment->task_id) {
                throw new CommentReplyOutsideTaskException;
            }
        });
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'author_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_comment_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_comment_id')->orderBy('created_at')->orderBy('id');
    }
}
