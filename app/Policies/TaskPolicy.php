<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Division;
use App\Models\Task;

/**
 * Task permissions follow the role permissions in role_permissions (TASK-12 RBAC matrix).
 *
 * Admins act across every division; other roles only within the division they belong to.
 */
class TaskPolicy
{
    /**
     * Deactivated accounts may not do anything in the Tasklist.
     */
    public function before(Account $user): ?bool
    {
        return $user->is_active ? null : false;
    }

    /**
     * Every active account with the view permission sees the tasks of every division.
     */
    public function viewAny(Account $user): bool
    {
        return $user->hasPermission('task.view_all');
    }

    /**
     * Seeing a task never grants the right to change it.
     */
    public function view(Account $user, Task $task): bool
    {
        return $user->hasPermission('task.view_all');
    }

    /**
     * Admins may create tasks for every division; managers and supervisors only for their own division.
     * Without a division, only the permission is checked.
     */
    public function create(Account $user, ?Division $division = null): bool
    {
        if (! $user->hasPermission('task.create')) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $user->division_id !== null && ($division === null || $user->division_id === $division->id);
    }

    /**
     * Admins, and managers or supervisors of the task's division, fully manage the task.
     */
    public function manage(Account $user, Task $task): bool
    {
        return $user->hasPermission('task.update_all') && $this->coversDivision($user, $task);
    }

    /**
     * Managers of the task may edit it; staff and freelancers only while they are its PIC.
     */
    public function update(Account $user, Task $task): bool
    {
        return $this->manage($user, $task)
            || ($user->hasPermission('task.update_assigned') && $task->hasActivePic($user));
    }

    /**
     * Deleting follows the same authority as editing.
     */
    public function delete(Account $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    /**
     * Assigning or forwarding the PIC is limited to the division the user manages.
     */
    public function assign(Account $user, Task $task): bool
    {
        return $user->hasPermission('task.assign') && $this->coversDivision($user, $task);
    }

    /**
     * Reviewing and approving is limited to the division the user manages.
     */
    public function review(Account $user, Task $task): bool
    {
        return $user->hasPermission('task.review') && $this->coversDivision($user, $task);
    }

    /**
     * Reviewers of the task and its current PIC may take part in the discussion; others read only.
     */
    public function comment(Account $user, Task $task): bool
    {
        return $this->review($user, $task) || $task->hasActivePic($user);
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
     * Admins cover every division; everyone else only their own division.
     */
    protected function coversDivision(Account $user, Task $task): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->division_id !== null && $user->division_id === $task->division_id;
    }
}
