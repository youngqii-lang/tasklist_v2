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

    public const string STATUS_BACKLOG = 'backlog';

    public const string STATUS_TODO = 'todo';

    public const string STATUS_IN_PROGRESS = 'in_progress';

    public const string STATUS_READY_REVIEW = 'ready_review';

    public const string STATUS_ON_REVIEW = 'on_review';

    public const string STATUS_DONE = 'done';

    /**
     * Statuses a task may start in when it is created.
     *
     * @var list<string>
     */
    public const array INITIAL_STATUSES = [self::STATUS_BACKLOG, self::STATUS_TODO];

    /**
     * Display labels for every status, in workflow order.
     *
     * @var array<string, string>
     */
    public const array STATUS_LABELS = [
        self::STATUS_BACKLOG => 'Back Log',
        self::STATUS_TODO => 'To Do',
        self::STATUS_IN_PROGRESS => 'On Process',
        self::STATUS_READY_REVIEW => 'Ready for QA',
        self::STATUS_ON_REVIEW => 'On QA',
        self::STATUS_DONE => 'Done',
    ];

    public const string PRIORITY_LOW = 'low';

    public const string PRIORITY_MEDIUM = 'medium';

    public const string PRIORITY_HIGH = 'high';

    public const string PRIORITY_URGENT = 'urgent';

    /**
     * @var list<string>
     */
    public const array PRIORITIES = [self::PRIORITY_LOW, self::PRIORITY_MEDIUM, self::PRIORITY_HIGH, self::PRIORITY_URGENT];

    /**
     * Display labels for every priority, from lowest to highest.
     *
     * @var array<string, string>
     */
    public const array PRIORITY_LABELS = [
        self::PRIORITY_LOW => 'Low',
        self::PRIORITY_MEDIUM => 'Medium',
        self::PRIORITY_HIGH => 'High',
        self::PRIORITY_URGENT => 'Urgent',
    ];

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
     * Get the accounts currently assigned as PIC.
     *
     * @return BelongsToMany<Account, $this>
     */
    public function pics(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'task_assignments')->wherePivotNull('unassigned_at');
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
