<?php

namespace App\Models;

use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'title',
    'description',
    'category_id',
    'division_id',
    'platform_id',
    'creator_id',
    'priority',
    'status',
    'start_date',
    'deadline',
    'requires_review',
    'evidence_required',
    'completed_at',
    'deleted_by',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Get the category of the task.
     *
     * @return BelongsTo<TaskCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TaskCategory::class, 'category_id');
    }

    /**
     * Get the division that owns the task.
     *
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Get the platform of the task.
     *
     * @return BelongsTo<Platform, $this>
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    /**
     * Get the account that created the task.
     *
     * @return BelongsTo<Account, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'creator_id');
    }

    /**
     * Get the account that soft deleted the task.
     *
     * @return BelongsTo<Account, $this>
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'deleted_by');
    }

    /**
     * Get the collaborating divisions, excluding the owning division.
     *
     * @return BelongsToMany<Division, $this>
     */
    public function collaboratorDivisions(): BelongsToMany
    {
        return $this->belongsToMany(Division::class, 'task_divisions')->withPivot(['added_by', 'created_at']);
    }

    /**
     * Get every assignment of the task, including past ones.
     *
     * @return HasMany<TaskAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    /**
     * Get the evidences submitted for the task.
     *
     * @return HasMany<TaskEvidence, $this>
     */
    public function evidences(): HasMany
    {
        return $this->hasMany(TaskEvidence::class);
    }

    /**
     * Get the review decisions of the task.
     *
     * @return HasMany<TaskReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(TaskReview::class);
    }

    /**
     * Get the comments on the task.
     *
     * @return HasMany<TaskComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class);
    }

    /**
     * Get the audit trail of the task.
     *
     * @return HasMany<TaskActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'deadline' => 'date',
            'requires_review' => 'boolean',
            'evidence_required' => 'boolean',
            'completed_at' => 'datetime',
            'version' => 'integer',
        ];
    }
}
