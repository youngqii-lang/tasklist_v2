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
     * Only managers and admins may create accounts. Accounts created by managers always start as employees.
     */
    public function create(Account $user): bool
    {
        return $user->isManager() || $user->isAdmin();
    }

    /**
     * Admins may update every account; managers only staff and freelance accounts.
     */
    public function update(Account $user, Account $account): bool
    {
        return $user->isAdmin() || ($user->isManager() && $account->isStaff());
    }

    /**
     * Deleting follows the same authority as updating, and nobody may delete their own account.
     */
    public function delete(Account $user, Account $account): bool
    {
        return $this->update($user, $account) && $user->isNot($account);
    }

    /**
     * Only admins may choose or change an account's role.
     */
    public function assignRole(Account $user): bool
    {
        return $user->isAdmin();
    }
}
