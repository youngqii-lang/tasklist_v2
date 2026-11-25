<?php

namespace App\Models;

use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['code', 'description'])]
class Permission extends Model
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    public const string TASK_VIEW_ALL = 'task.view_all';

    public const string TASK_CREATE = 'task.create';

    public const string TASK_UPDATE = 'task.update';

    public const string TASK_DELETE = 'task.delete';

    public const string TASK_ASSIGN = 'task.assign';

    public const string TASK_REVIEW = 'task.review';

    public const string TASK_CHANGE_STATUS = 'task.change_status';

    /**
     * Get the roles that are granted this permission.
     *
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions');
    }
}
