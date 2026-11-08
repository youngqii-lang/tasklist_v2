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
     * Only managers and admins may create accounts.
     */
    public function create(Account $user): bool
    {
        return $this->managesAccounts($user);
    }

    /**
     * Only managers and admins may update accounts.
     */
    public function update(Account $user, Account $account): bool
    {
        return $this->managesAccounts($user);
    }

    /**
     * Only managers and admins may delete accounts, and never their own account.
     */
    public function delete(Account $user, Account $account): bool
    {
        return $this->managesAccounts($user) && $user->isNot($account);
    }

    /**
     * Admins may choose the role of a new account; managers always create employees.
     */
    public function assignRoleOnCreate(Account $user): bool
    {
        return $user->isAdmin();
    }

    private function managesAccounts(Account $user): bool
    {
        return $user->isManager() || $user->isAdmin();
    }
}
