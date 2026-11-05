<?php

namespace App\Models;

use App\Exceptions\StaleTaskVersionException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
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
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'version' => 1,
    ];

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
     * Statuses in which the work has been handed in for review or is finished.
     *
     * @var list<string>
     */
    public const array REVIEW_STAGE_STATUSES = [self::STATUS_READY_REVIEW, self::STATUS_ON_REVIEW, self::STATUS_DONE];

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
     * Determine whether the task is still open after its deadline has passed.
     */
    public function isOverdue(): bool
    {
        return $this->status !== self::STATUS_DONE && $this->deadline !== null && $this->deadline->lt(today());
    }

    /**
     * Determine whether the account is currently a PIC of the task.
     */
    public function hasActivePic(Account $account): bool
    {
        if ($this->relationLoaded('pics')) {
            return $this->pics->contains($account);
        }

        return $this->assignments()->where('account_id', $account->id)->whereNull('unassigned_at')->exists();
    }

    /**
     * Reload the task with a row lock and make sure nobody changed it since the client loaded it.
     *
     * @throws StaleTaskVersionException
     */
    public function lockForVersion(int $expectedVersion): static
    {
        $task = static::query()->lockForUpdate()->findOrFail($this->getKey());

        if ($task->version !== $expectedVersion) {
            throw new StaleTaskVersionException;
        }

        return $task;
    }

    /**
     * Append an entry to the audit trail of the task.
     *
     * @param  array<string, mixed>  $changes
     */
    public function recordActivity(?Account $account, string $action, array $changes = []): TaskActivity
    {
        return $this->activities()->create([
            'account_id' => $account?->id,
            'action' => $action,
            'changes' => $changes,
        ]);
    }

    /**
     * Limit the query to tasks the account is allowed to see.
     *
     * Active accounts with the view permission see every division; others see nothing.
     *
     * @param  Builder<Task>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, Account $account): void
    {
        if (! $account->is_active || ! $account->hasPermission('task.view_all')) {
            $query->whereRaw('1 = 0');
        }
    }

    /**
     * Apply the Tasklist search and filters.
     *
     * The period filters (date, month, year, date range) apply to `date_field` (start_date or deadline, default deadline);
     * tasks without that date never match a period filter.
     *
     * @param  Builder<Task>  $query
     * @param  array{
     *     search?: string|null,
     *     division_id?: int|string|null,
     *     pic_id?: int|string|null,
     *     priority?: string|null,
     *     status?: string|null,
     *     date_field?: string|null,
     *     date?: string|null,
     *     month?: string|null,
     *     year?: int|string|null,
     *     date_from?: string|null,
     *     date_to?: string|null,
     * }  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $dateField = ($filters['date_field'] ?? null) === 'start_date' ? 'start_date' : 'deadline';
        [$periodStart, $periodEnd] = self::periodBounds($filters);

        $query
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query
                ->where('title', 'like', '%'.addcslashes($search, '%_\\').'%'))
            ->when($filters['division_id'] ?? null, fn (Builder $query, $divisionId) => $query->where('division_id', (int) $divisionId))
            ->when($filters['pic_id'] ?? null, fn (Builder $query, $picId) => $query->whereHas('assignments', fn (Builder $assignments) => $assignments
                ->where('account_id', (int) $picId)
                ->whereNull('unassigned_at')))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($periodStart !== null || $periodEnd !== null, fn (Builder $query) => $query->whereNotNull($dateField))
            ->when($periodStart, fn (Builder $query, CarbonInterface $start) => $query->whereDate($dateField, '>=', $start))
            ->when($periodEnd, fn (Builder $query, CarbonInterface $end) => $query->whereDate($dateField, '<=', $end));
    }

    /**
     * Combine the date, month, year and range filters into one inclusive period. Every given filter narrows the period.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: CarbonInterface|null, 1: CarbonInterface|null}
     */
    protected static function periodBounds(array $filters): array
    {
        $ranges = [];

        if (! empty($filters['date'])) {
            $day = CarbonImmutable::parse($filters['date']);
            $ranges[] = [$day, $day];
        }

        if (! empty($filters['month'])) {
            $month = CarbonImmutable::createFromFormat('Y-m-d', $filters['month'].'-01');
            $ranges[] = [$month->startOfMonth(), $month->endOfMonth()];
        }

        if (! empty($filters['year'])) {
            $year = CarbonImmutable::create((int) $filters['year']);
            $ranges[] = [$year->startOfYear(), $year->endOfYear()];
        }

        $ranges[] = [
            empty($filters['date_from']) ? null : CarbonImmutable::parse($filters['date_from']),
            empty($filters['date_to']) ? null : CarbonImmutable::parse($filters['date_to']),
        ];

        $starts = array_filter(array_column($ranges, 0));
        $ends = array_filter(array_column($ranges, 1));

        return [$starts === [] ? null : max($starts), $ends === [] ? null : min($ends)];
    }

    /**
     * Order the query by a Tasklist column. Priority and status follow their business order, not the alphabet.
     *
     * @param  Builder<Task>  $query
     */
    #[Scope]
    protected function sortBy(Builder $query, string $column, string $direction): void
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';
        $orderedValues = match ($column) {
            'priority' => self::PRIORITIES,
            'status' => array_keys(self::STATUS_LABELS),
            default => null,
        };

        if ($orderedValues === null) {
            $query->orderBy($column, $direction);
        } else {
            $cases = collect($orderedValues)->keys()->map(fn (int $position) => "WHEN ? THEN {$position}")->implode(' ');
            $query->orderByRaw("CASE {$column} {$cases} END {$direction}", $orderedValues);
        }

        $query->orderBy('id', $direction);
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
