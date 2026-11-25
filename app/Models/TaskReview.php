<?php

namespace App\Models;

use App\Enums\TaskReviewDecision;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One review decision on a task. Reviews are never edited once recorded. Their cycle (the first review is 1, each
 * resubmission after a revision the next number) is not stored but counted with withCycle().
 */
#[Fillable(['task_id', 'reviewer_id', 'decision', 'note'])]
class TaskReview extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decision' => TaskReviewDecision::class,
            'cycle' => 'integer',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'reviewer_id');
    }

    /**
     * Add cycle: how many reviews of the same task were recorded up to and including this one.
     */
    public function scopeWithCycle(Builder $query): void
    {
        $query->when($query->getQuery()->columns === null, fn (Builder $query) => $query->select('task_reviews.*'))
            ->selectSub(fn ($sub) => $sub->from('task_reviews as earlier')
                ->selectRaw('count(*)')
                ->whereColumn('earlier.task_id', 'task_reviews.task_id')
                ->where(fn ($sub) => $sub->whereColumn('earlier.created_at', '<', 'task_reviews.created_at')
                    ->orWhere(fn ($sub) => $sub->whereColumn('earlier.created_at', 'task_reviews.created_at')
                        ->whereColumn('earlier.id', '<=', 'task_reviews.id'))), 'cycle');
    }
}
