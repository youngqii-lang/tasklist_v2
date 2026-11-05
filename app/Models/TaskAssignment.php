<?php

namespace App\Models;

use Database\Factories\TaskAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['task_id', 'account_id', 'assigned_by', 'assigned_at', 'unassigned_by', 'unassigned_at', 'note'])]
#[WithoutTimestamps]
class TaskAssignment extends Model
{
    /** @use HasFactory<TaskAssignmentFactory> */
    use HasFactory;

    /**
     * Get the assigned task.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the account assigned as PIC.
     *
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the account that made the assignment.
     *
     * @return BelongsTo<Account, $this>
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'assigned_by');
    }

    /**
     * Get the account that ended the assignment.
     *
     * @return BelongsTo<Account, $this>
     */
    public function unassigner(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'unassigned_by');
    }

    /**
     * Scope the query to assignments that have not been ended.
     *
     * @param  Builder<TaskAssignment>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereNull('unassigned_at');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
        ];
    }
}
