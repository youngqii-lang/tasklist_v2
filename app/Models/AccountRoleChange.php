<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One role an account was given: the role before (null at creation), the role after, who changed it, and when.
 * Append-only audit; the application never updates or deletes these rows. The names and usernames are copied in
 * when the row is written, so it still says who was changed and by whom after either account is deleted.
 */
#[Fillable([
    'account_id', 'account_fullname', 'account_username', 'old_role_id', 'new_role_id',
    'changed_by', 'changed_by_fullname', 'changed_by_username', 'changed_at',
])]
class AccountRoleChange extends Model
{
    public $timestamps = false;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'changed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function oldRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'old_role_id');
    }

    public function newRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'new_role_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'changed_by');
    }

    /**
     * Record that the actor gave the account its current role; the old role is null when the account was just created.
     */
    public static function record(Account $account, ?int $oldRoleId, Account $actor): self
    {
        return self::create([
            'account_id' => $account->id,
            'account_fullname' => $account->fullname,
            'account_username' => $account->username,
            'old_role_id' => $oldRoleId,
            'new_role_id' => $account->role_id,
            'changed_by' => $actor->id,
            'changed_by_fullname' => $actor->fullname,
            'changed_by_username' => $actor->username,
            'changed_at' => now(),
        ]);
    }
}
