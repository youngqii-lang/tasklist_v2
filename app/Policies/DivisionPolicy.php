<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Division;

class DivisionPolicy
{
    /**
     * Every signed-in account may view the division list.
     */
    public function viewAny(Account $user): bool
    {
        return true;
    }

    /**
     * Only managers may create divisions.
     */
    public function create(Account $user): bool
    {
        return $user->isManager();
    }

    /**
     * Only managers may update divisions.
     */
    public function update(Account $user, Division $division): bool
    {
        return $user->isManager();
    }

    /**
     * Only managers may delete divisions.
     */
    public function delete(Account $user, Division $division): bool
    {
        return $user->isManager();
    }
}
