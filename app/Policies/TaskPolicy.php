<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Division;

class TaskPolicy
{
    /**
     * Admins may create tasks for every division; managers only for their own division.
     * Without a division, only the role is checked.
     */
    public function create(Account $user, ?Division $division = null): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if (! $user->isManager() || $user->division_id === null) {
            return false;
        }

        return $division === null || $user->division_id === $division->id;
    }
}
