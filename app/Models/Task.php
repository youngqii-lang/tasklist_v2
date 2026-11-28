<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Carbon\CarbonInterface;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** PIC assignments live exclusively in task_assignments. */
#[Fillable([
    'title', 'description', 'category_id', 'division_id', 'platform_id', 'creator_id', 'priority', 'status',
    'start_date', 'deadline', 'deadline_time', 'requires_review', 'evidence_required', 'completed_at', 'deleted_by',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, SoftDeletes;

    /**
     * Fields an Admin or the division's Manager may change through the edit form. PICs are changed through the
     * assignment endpoint, not this form. Review and evidence are required for every task, so they are not editable.
     */
    public const EDITABLE_FIELDS = [
        'title', 'description', 'category_id', 'division_id', 'collaborating_division_ids', 'platform_id', 'priority',
        'status', 'start_date', 'deadline', 'deadline_time',
    ];

    /**
     * The time a deadline without its own time ends at.
     */
    public const END_OF_DAY = '23:59';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'version' => 1,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'start_date' => 'date',
            'deadline' => 'date',
            'requires_review' => 'boolean',
            'evidence_required' => 'boolean',
            'completed_at' => 'datetime',
            'version' => 'integer',
        ];
    }

    /**
     * The deadline's time of day as HH:MM. It is stored as HH:MM:SS so it compares the same way on every database.
     */
    protected function deadlineTime(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : substr($value, 0, 5),
            set: fn (?string $value) => $value === null || $value === '' ? null : substr($value, 0, 5).':00',
        );
    }

    /**
     * The moment the deadline ends: its date at its time, or at the end of that day when it has no time.
     */
    public function deadlineAt(): ?CarbonInterface
    {
        return $this->deadline?->copy()->setTimeFromTimeString($this->deadline_time ?? self::END_OF_DAY);
    }

    /**
     * An unfinished task is overdue once the moment of its deadline has passed.
     */
    public function isOverdue(): bool
    {
        return $this->status !== TaskStatus::Done && $this->deadlineAt()?->isPast() === true;
    }

    /**
     * History, newest first.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(TaskEvidence::class)->latest()->chaperone();
    }

    /**
     * review decisions, newest first.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(TaskReview::class)->withCycle()->orderByDesc('created_at')->orderByDesc('id');
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TaskCategory::class);
    }

    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'creator_id');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'deleted_by');
    }

    /**
     * Every assignment, active and ended, oldest first.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class)->orderBy('assigned_at')->orderBy('id');
    }

    public function activeAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class)->whereNull('unassigned_at');
    }

    /**
     * The active PICs, by name.
     */
    public function pics(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'task_assignments')
            ->wherePivotNull('unassigned_at')
            ->withPivot(['id', 'assigned_by', 'assigned_at'])
            ->orderBy('accounts.fullname')
            ->orderBy('accounts.id');
    }

    /**
     * Collaborating divisions, besides the owning division in division_id. Attaching the owning division is
     * rejected by TaskDivision.
     */
    public function collaboratingDivisions(): BelongsToMany
    {
        return $this->belongsToMany(Division::class, 'task_divisions')
            ->using(TaskDivision::class)
            // created_at is set by TaskDivision; listing it here would make Laravel also write a missing updated_at.
            ->withPivot(['added_by']);
    }

    /**
     * Tasks the account is an active PIC of. A subquery, not a join, so a task with several PICs is listed once.
     */
    public function scopeAssignedTo(Builder $query, Account $account): void
    {
        $query->whereHas('activeAssignments', fn (Builder $query) => $query->where('account_id', $account->id));
    }

    /**
     * Comments and replies, oldest first.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * Limit tasks to what the account may see. Every active account whose role grants task.view_all sees the tasks
     * of every division (TASK-19/TASK-12); seeing a task never grants changing it. An inactive account sees nothing.
     */
    public function scopeVisibleTo(Builder $query, Account $account): void
    {
        if (! $account->is_active) {
            $query->whereRaw('1 = 0');

            return;
        }

        if (! $account->hasPermission(Permission::TASK_VIEW_ALL)) {
            $query->assignedTo($account);
        }
    }

    /**
     * Same rule as scopeVisibleTo(), checked against a loaded task.
     */
    public function isVisibleTo(Account $account): bool
    {
        return $account->is_active && ($account->hasPermission(Permission::TASK_VIEW_ALL) || $this->assignedTo($account));
    }

    /**
     * Who may add or release PICs and forward the task: an account with task.assign that manages the task.
     */
    public function assignableBy(Account $account): bool
    {
        return $this->managedBy($account, Permission::TASK_ASSIGN);
    }

    /**
     * Who may move the task to another division: Admin and Managers, who work across divisions. A Supervisor manages
     * the PICs of their own division's tasks but does not hand them to another division.
     */
    public function forwardableBy(Account $account): bool
    {
        return $this->assignableBy($account) && $account->assignsPicsAcrossDivisions();
    }

    /**
     * Whether the account manages this task: Admin and Managers for every division, a Supervisor for the division
     * they manage. With a permission, the account's role must also grant it. Staff and Freelance never manage a
     * task; they work on it as its PIC.
     */
    public function managedBy(Account $account, ?string $permission = null): bool
    {
        return $account->is_active
            && self::managesDivision($account, $this->division_id)
            && ($permission === null || $account->hasPermission($permission));
    }

    /**
     * The management scope of a role: Admin and Manager cover every division, a Supervisor their own division.
     */
    public static function managesDivision(Account $account, ?int $divisionId): bool
    {
        return $account->isAdmin()
            || $account->isManager()
            || ($account->isSupervisor() && $account->division_id !== null && $divisionId === $account->division_id);
    }

    /**
     * Whether the account is one of the task's active PICs. Every active PIC may run the shared workflow.
     */
    public function assignedTo(Account $account): bool
    {
        if (! $account->is_active) {
            return false;
        }

        if ($this->relationLoaded('pics')) {
            return $this->pics->contains('id', $account->id);
        }

        return $this->activeAssignments()->where('account_id', $account->id)->exists();
    }

    public function hasActivePic(): bool
    {
        return $this->relationLoaded('pics') ? $this->pics->isNotEmpty() : $this->activeAssignments()->exists();
    }

    /**
     * Seeing a task is not editing it: Admin, Managers, and the division's Supervisor may edit it until it is Done,
     * its PIC only while there is a status step for them to take. Otherwise the task is read-only.
     */
    public function updatableBy(Account $account): bool
    {
        return ($this->managedBy($account, Permission::TASK_UPDATE) && $this->status !== TaskStatus::Done)
            || $this->nextStatusesFor($account) !== [];
    }

    /**
     * Statuses the account may move the task to from its current one: every active PIC, and Admin, a Manager, or the
     * division's Supervisor, moves it from Back Log up to Ready Review; only those reviewers start the review or
     * finish a task. Leaving On Review is a review decision, never a plain move.
     *
     * @return array<int, TaskStatus>
     */
    public function nextStatusesFor(Account $account): array
    {
        if ($this->status->decidedByReview()) {
            return [];
        }

        return array_values(array_filter(
            $this->status->nextStatuses($this->requires_review),
            fn (TaskStatus $status) => $this->movesStatusFor($account, $status),
        ));
    }

    /**
     * Whether the account may review this task: Admin and Managers for every division, a Supervisor for their own,
     * and only for a task that requires review. Being one of its active PICs does not take that away, so a Manager
     * or Supervisor may approve their own work (self-approval). Staff and Freelance never review.
     */
    public function reviewableBy(Account $account): bool
    {
        return $this->requires_review && $this->managedBy($account, Permission::TASK_REVIEW);
    }

    /**
     * Who takes part in the task's discussion: Admin and Managers for every division, the Supervisor of its division,
     * and its active PICs. A PIC who was released, and anyone else, only reads it.
     */
    public function commentableBy(Account $account): bool
    {
        return $this->managedBy($account) || $this->assignedTo($account);
    }

    /**
     * Whether the account may add a comment right now: a Done task keeps its discussion read-only.
     */
    public function acceptsCommentFrom(Account $account): bool
    {
        return $this->status !== TaskStatus::Done && $this->commentableBy($account);
    }

    /**
     * Whether the account may approve the task or request a revision right now.
     */
    public function awaitsDecisionFrom(Account $account): bool
    {
        return $this->status === TaskStatus::OnReview && $this->reviewableBy($account);
    }

    /**
     * Why the account may not move the task from its current status to the given one, or null when it may.
     */
    public function statusChangeDenialFor(Account $account, TaskStatus $status): ?string
    {
        $from = $this->status;

        if (! $from->canMoveTo($status, $this->requires_review)) {
            $reason = "Status tidak dapat diubah dari {$from->label()} ke {$status->label()}.";

            return $status === TaskStatus::Done && $this->requires_review
                ? $reason.' Tugas ini wajib melalui Ready Review dan On Review sebelum Done.'
                : $reason;
        }

        if (! $this->movesStatusFor($account, $status)) {
            return $status->reachedByReviewer()
                ? "Hanya Admin, Manajer, atau Supervisor divisi yang dapat mengubah status dari {$from->label()} ke {$status->label()}."
                : "Hanya PIC aktif, Admin, Manajer, atau Supervisor divisi yang dapat mengubah status dari {$from->label()} ke {$status->label()}.";
        }

        if ($from->decidedByReview()) {
            return 'Keputusan review diberikan melalui Approve atau Request Revision.';
        }

        if ($from === TaskStatus::InProgress && $this->evidence_required && ! $this->evidences()->exists()) {
            return "Unggah evidence terlebih dahulu sebelum mengubah status ke {$status->label()}.";
        }

        return null;
    }

    /**
     * A task that already has a review got there through a revision, so handing it to review again is a resubmission.
     */
    public function resubmitsTo(TaskStatus $status): bool
    {
        return $status === TaskStatus::ReadyReview && $this->reviews()->exists();
    }

    /**
     * Whether the account may make a move into the given status: an active PIC or an account that manages the task
     * up to Ready Review, and only an account that manages it with task.review into On Review or Done. Being a PIC
     * does not take that away, so a Manager or Supervisor may also move their own task.
     */
    private function movesStatusFor(Account $account, TaskStatus $status): bool
    {
        if (! $account->hasPermission(Permission::TASK_CHANGE_STATUS)) {
            return false;
        }

        return $status->reachedByReviewer()
            ? $this->managedBy($account, Permission::TASK_REVIEW)
            : $this->assignedTo($account) || $this->managedBy($account);
    }

    /**
     * Editable fields the account may not change in the task's current condition, with the reason.
     *
     * @return array<string, string>
     */
    public function lockedFieldsFor(Account $account): array
    {
        if (! $this->managedBy($account, Permission::TASK_UPDATE)) {
            return collect(self::EDITABLE_FIELDS)
                ->reject(fn (string $field) => $field === 'status')
                ->mapWithKeys(fn (string $field) => [$field => 'PIC hanya dapat mengubah status tugas.'])
                ->all();
        }

        if ($this->status === TaskStatus::Done) {
            return collect(self::EDITABLE_FIELDS)
                ->mapWithKeys(fn (string $field) => [$field => 'Tugas yang sudah selesai tidak dapat diubah.'])
                ->all();
        }

        $locked = [];

        if (! $this->forwardableBy($account)) {
            $locked['division_id'] = 'Hanya Admin dan Manajer yang dapat memindahkan tugas ke divisi lain.';
        }

        return $locked;
    }

    /**
     * Whether the account may delete the task right now: it manages the task with task.delete, and the task is not
     * Done (a finished task stays as the record of the work).
     */
    public function deletableBy(Account $account): bool
    {
        return $this->status !== TaskStatus::Done && $this->managedBy($account, Permission::TASK_DELETE);
    }

    /**
     * Why the account may not add or release PICs right now, or null when it may.
     */
    public function assignmentLockFor(Account $account): ?string
    {
        return match (true) {
            ! $this->assignableBy($account) => 'Anda tidak berwenang mengatur PIC tugas ini.',
            $this->status === TaskStatus::Done => 'Tugas yang sudah selesai tidak dapat diubah.',
            $this->status->inReview() => 'PIC tidak dapat diganti selama tugas dalam tahap review.',
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    public function editableFieldsFor(Account $account): array
    {
        if (! $this->updatableBy($account)) {
            return [];
        }

        $fields = array_diff(self::EDITABLE_FIELDS, array_keys($this->lockedFieldsFor($account)));

        if ($this->nextStatusesFor($account) === []) {
            $fields = array_diff($fields, ['status']);
        }

        return array_values($fields);
    }
}
