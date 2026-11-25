<?php

namespace App\Models;

use Database\Factories\DivisionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code_division', 'name'])]
class Division extends Model
{
    /** @use HasFactory<DivisionFactory> */
    use HasFactory;

    /**
     * Get the accounts that belong to this division.
     *
     * @return HasMany<Account, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * Get the tasks owned by this division.
     *
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * Get the tasks this division collaborates on, besides the tasks it owns.
     *
     * @return BelongsToMany<Task, $this>
     */
    public function collaboratingTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_divisions')
            ->using(TaskDivision::class)
            ->withPivot(['added_by']);
    }

    /**
     * Limit the query to divisions the account may create tasks for: every division for Admin and Managers, their
     * own division for a Supervisor, none for anyone else.
     *
     * @param  Builder<Division>  $query
     */
    #[Scope]
    protected function taskCreatableBy(Builder $query, Account $account): void
    {
        if (! $account->is_active || ! $account->hasPermission(Permission::TASK_CREATE)) {
            $query->whereKey(0);

            return;
        }

        if ($account->isAdmin() || $account->isManager()) {
            return;
        }

        $query->whereKey($account->isSupervisor() ? ($account->division_id ?? 0) : 0);
    }
}
