<?php

namespace App\Models;

use App\Exceptions\AccountHasTaskHistoryException;
use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['role_id', 'division_id', 'fullname', 'username', 'password', 'is_active'])]
#[Hidden(['password'])]
class Account extends Authenticatable
{
    /** @use HasFactory<AccountFactory> */
    use HasFactory;

    /**
     * The model's default values for attributes, matching the database defaults.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Get the role assigned to the account.
     *
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Get the division the account belongs to.
     *
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * Determine whether the account has the admin role.
     */
    public function isAdmin(): bool
    {
        return $this->role?->code_role === Role::ADMIN;
    }

    /**
     * Determine whether the role of the account grants the permission code.
     *
     * Permissions are read from the current role, so a role change takes effect immediately.
     */
    public function hasPermission(string $code): bool
    {
        return (bool) $this->role?->permissions->contains('code', $code);
    }

    /**
     * Determine whether the account has the manager role.
     */
    public function isManager(): bool
    {
        return $this->role?->code_role === Role::MANAGER;
    }

    /**
     * Determine whether the account has a staff role (employee or freelance).
     */
    public function isStaff(): bool
    {
        return in_array($this->role?->code_role, Role::STAFF_ROLES, true);
    }

    /**
     * Determine whether task data still refers to the account, so deleting it would break the task history.
     */
    public function hasTaskHistory(): bool
    {
        return TaskAssignment::where('account_id', $this->id)->exists()
            || TaskComment::where('author_id', $this->id)->exists()
            || CalendarEvent::where('creator_id', $this->id)->exists();
    }

    /**
     * @throws AccountHasTaskHistoryException
     */
    public function ensureCanBeDeleted(): void
    {
        if ($this->hasTaskHistory()) {
            throw new AccountHasTaskHistoryException;
        }
    }

    /**
     * Determine whether the Tasklist authority of the account covers every division (Admin and Manajer).
     */
    public function managesAllDivisions(): bool
    {
        return in_array($this->role?->code_role, Role::ALL_DIVISION_ROLES, true);
    }

    /**
     * Determine whether the account has the supervisor role.
     */
    public function isSupervisor(): bool
    {
        return $this->role?->code_role === Role::SUPERVISOR;
    }

    /**
     * Admin and Managers work across divisions: they assign PICs from, and move tasks to, every division.
     */
    public function assignsPicsAcrossDivisions(): bool
    {
        return $this->isAdmin() || $this->isManager();
    }

    /**
     * Codes of every permission the account's role grants, sorted.
     *
     * @return array<int, string>
     */
    public function permissionCodes(): array
    {
        return $this->role?->permissions->pluck('code')->sort()->values()->all() ?? [];
    }

    /**
     * Get every PIC assignment of the account, active and released.
     *
     * @return HasMany<TaskAssignment, $this>
     */
    public function taskAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    /**
     * Get the tasks the account is an active PIC of.
     *
     * @return BelongsToMany<Task, $this>
     */
    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_assignments')
            ->wherePivotNull('unassigned_at')
            ->withPivot(['id', 'assigned_by', 'assigned_at']);
    }

    /**
     * Get the role audit of the account, oldest first.
     *
     * @return HasMany<AccountRoleChange, $this>
     */
    public function roleChanges(): HasMany
    {
        return $this->hasMany(AccountRoleChange::class)->orderBy('changed_at')->orderBy('id');
    }

    /**
     * Get the task comments written by the account.
     *
     * @return HasMany<TaskComment, $this>
     */
    public function taskComments(): HasMany
    {
        return $this->hasMany(TaskComment::class, 'author_id');
    }

    /**
     * Limit the query to active Staff, Freelance, and Supervisors of any division, who can be assigned as task PIC.
     *
     * @param  Builder<Account>  $query
     */
    #[Scope]
    protected function assignableAsPic(Builder $query, ?int $divisionId = null): void
    {
        $query->where('is_active', true)
            ->whereHas('role', fn (Builder $role) => $role->whereIn('code_role', Role::PIC_ROLES))
            ->when($divisionId !== null, fn (Builder $accounts) => $accounts->where('division_id', $divisionId));
    }

    /**
     * Limit the query to accounts the assigner may add as a new PIC: Admin and Managers from every division, a
     * Supervisor only from the division they manage. Anyone else may not assign PICs.
     *
     * @param  Builder<Account>  $query
     */
    #[Scope]
    protected function assignableAsPicBy(Builder $query, Account $assigner): void
    {
        $query->assignableAsPic();

        if (! $assigner->assignsPicsAcrossDivisions()) {
            $query->where('division_id', $assigner->isSupervisor() ? ($assigner->division_id ?? 0) : 0);
        }
    }

    /**
     * Disable "remember me" tokens because the accounts table has no remember_token column.
     */
    public function getRememberTokenName(): string
    {
        return '';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }
}
