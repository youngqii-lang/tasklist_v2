<?php

namespace App\Models;

use Database\Factories\TaskCommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['task_id', 'author_id', 'parent_comment_id', 'content'])]
class TaskComment extends Model
{
    /** @use HasFactory<TaskCommentFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the task the comment belongs to.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the account that wrote the comment.
     *
     * @return BelongsTo<Account, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'author_id');
    }

    /**
     * Get the comment this comment replies to.
     *
     * @return BelongsTo<TaskComment, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(TaskComment::class, 'parent_comment_id');
    }

    /**
     * Get the replies to this comment.
     *
     * @return HasMany<TaskComment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(TaskComment::class, 'parent_comment_id');
    }
}
