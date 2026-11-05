<?php

namespace App\Models;

use Database\Factories\TaskReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['task_id', 'reviewer_id', 'decision', 'note'])]
class TaskReview extends Model
{
    /** @use HasFactory<TaskReviewFactory> */
    use HasFactory;

    /**
     * Get the reviewed task.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the account that reviewed the task.
     *
     * @return BelongsTo<Account, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'reviewer_id');
    }
}
