<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code_role', 'name'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    public const string ADMIN = 'admin';

    public const string MANAGER = 'manager';

    public const string SUPERVISOR = 'supervisor';

    public const string EMPLOYEE = 'employee';

    public const string FREELANCE = 'freelance';

    /**
     * Roles whose accounts can be assigned as task PIC.
     *
     * @var list<string>
     */
    public const array STAFF_ROLES = [self::EMPLOYEE, self::FREELANCE];

    /**
     * Get the accounts that have this role.
     *
     * @return HasMany<Account, $this>
     */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /**
     * Get the permissions granted to this role.
     *
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }
}
