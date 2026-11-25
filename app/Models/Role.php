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
     * Roles whose accounts can be assigned as task PIC. Admins maintain the system and are never PIC.
     *
     * @var list<string>
     */
    public const array PIC_ROLES = [self::MANAGER, self::SUPERVISOR, self::EMPLOYEE, self::FREELANCE];

    /**
     * Staff roles (Staff and Freelance). Managers may only manage the accounts of these roles.
     *
     * @var list<string>
     */
    public const array STAFF_ROLES = [self::EMPLOYEE, self::FREELANCE];

    /**
     * Roles whose authority over the Tasklist covers every division.
     *
     * @var list<string>
     */
    public const array ALL_DIVISION_ROLES = [self::ADMIN, self::MANAGER];

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
