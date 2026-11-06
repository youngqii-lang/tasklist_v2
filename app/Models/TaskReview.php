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

    public const string DECISION_APPROVED = 'approved';

    public const string DECISION_REVISION_REQUESTED = 'revision_requested';

    /**
     * Display labels for every review decision.
     *
     * @var array<string, string>
     */
    public const array DECISION_LABELS = [
        self::DECISION_APPROVED => 'Approve',
        self::DECISION_REVISION_REQUESTED => 'Request Revision',
    ];

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
