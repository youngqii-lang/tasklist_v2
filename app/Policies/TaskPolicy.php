<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Division;
use App\Models\Task;

class TaskPolicy
{
    /**
     * Every signed-in account may open the Tasklist; the visible tasks are limited by the query scope.
     */
    public function viewAny(Account $user): bool
    {
        return true;
    }

    /**
     * Admins see every task, managers the tasks of their division, and everyone else only the tasks they are PIC of.
     */
    public function view(Account $user, Task $task): bool
    {
        return $this->manages($user, $task) || $task->hasActivePic($user);
    }

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

    /**
     * Only admins and the manager of the task's division may edit the main task information.
     */
    public function update(Account $user, Task $task): bool
    {
        return $this->manages($user, $task);
    }

    /**
     * Admins may assign or forward the PIC of every task; managers only within their own division.
     */
    public function assign(Account $user, Task $task): bool
    {
        return $this->manages($user, $task);
    }

    /**
     * Only a current PIC may submit evidence of the work.
     */
    public function addEvidence(Account $user, Task $task): bool
    {
        return $task->hasActivePic($user);
    }

    /**
     * Only admins may move a task to another division.
     */
    public function changeDivision(Account $user, Task $task, Division $division): bool
    {
        return $task->division_id === $division->id || $user->isAdmin();
    }

    /**
     * Admins manage every task; managers only the tasks of their own division.
     */
    protected function manages(Account $user, Task $task): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->isManager() && $user->division_id !== null && $user->division_id === $task->division_id;
    }
}
