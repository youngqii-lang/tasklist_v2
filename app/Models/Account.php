<?php

namespace App\Models;

use Database\Factories\AccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['role_id', 'division_id', 'fullname', 'username', 'password'])]
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
     * Limit the query to active staff accounts that can be assigned as task PIC.
     *
     * @param  Builder<Account>  $query
     */
    #[Scope]
    protected function assignableAsPic(Builder $query, ?int $divisionId = null): void
    {
        $query->where('is_active', true)
            ->whereHas('role', fn (Builder $role) => $role->whereIn('code_role', Role::STAFF_ROLES))
            ->when($divisionId !== null, fn (Builder $accounts) => $accounts->where('division_id', $divisionId));
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
