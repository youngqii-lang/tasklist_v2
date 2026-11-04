<?php

namespace App\Policies;

use App\Models\Account;

class AccountPolicy
{
    /**
     * Every signed-in account may view the account list.
     */
    public function viewAny(Account $user): bool
    {
        return true;
    }

    /**
     * Only managers may create accounts.
     */
    public function create(Account $user): bool
    {
        return $user->isManager();
    }

    /**
     * Only managers may update accounts.
     */
    public function update(Account $user, Account $account): bool
    {
        return $user->isManager();
    }

    /**
     * Only managers may delete accounts, and never their own account.
     */
    public function delete(Account $user, Account $account): bool
    {
        return $user->isManager() && $user->isNot($account);
    }
}
