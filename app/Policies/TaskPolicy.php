<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\Division;
use App\Models\Permission;
use App\Models\Task;

class TaskPolicy
{
    /**
     * Every active account can open the task list; its rows are scoped by Task::scopeVisibleTo().
     */
    public function viewAny(Account $user): bool
    {
        return $user->is_active;
    }

    /**
     * Every active account sees the tasks of every division (global read); seeing grants no change.
     */
    public function view(Account $user, Task $task): bool
    {
        return $task->isVisibleTo($user);
    }

    /**
     * Admin and Managers create tasks for every division, a Supervisor for the division they manage; Staff and
     * Freelance cannot create tasks. Which division is checked by StoreTaskRequest.
     */
    public function create(Account $user): bool
    {
        return $user->is_active
            && $user->hasPermission(Permission::TASK_CREATE)
            && Division::query()->taskCreatableBy($user)->exists();
    }

    /**
     * Admin, Managers, and the division's Supervisor may edit the task; its active PICs may only update its status.
     */
    public function update(Account $user, Task $task): bool
    {
        return $task->updatableBy($user);
    }

    /**
     * Admin and Managers may delete tasks of every division, a Supervisor tasks of their own division; Staff and
     * Freelance never, not even as its PIC. Whether the task's status still allows it is checked by TaskService so the
     * rejection carries its reason.
     */
    public function delete(Account $user, Task $task): bool
    {
        return $task->managedBy($user, Permission::TASK_DELETE);
    }

    /**
     * Admin and Managers may add or release PICs and forward tasks of every division, a Supervisor tasks of their
     * own division; Staff and Freelance never.
     */
    public function assign(Account $user, Task $task): bool
    {
        return $task->assignableBy($user);
    }

    /**
     * Only accounts that take part in the workflow may request a status change: an active PIC, or an account that
     * manages the task. Seeing the task is not enough (403). Whether this account may make this particular move
     * (PIC vs. Admin/Manager/Supervisor) is checked by TaskService so the rejection carries its reason (422).
     */
    public function changeStatus(Account $user, Task $task): bool
    {
        return $user->hasPermission(Permission::TASK_CHANGE_STATUS)
            && ($task->assignedTo($user) || $task->managedBy($user));
    }

    /**
     * Admin and Managers for every division, and the Supervisor of the task's division, may approve a task that
     * requires review or request its revision, also when they are its PIC (self-approval); Staff and Freelance only
     * see the result. Whether the task is in On Review is checked by TaskReviewService so the rejection carries its
     * reason.
     */
    public function review(Account $user, Task $task): bool
    {
        return $task->reviewableBy($user);
    }

    /**
     * Only the task's active PICs send evidence for it; everyone else sees it through view(). Whether the task's
     * status still accepts evidence is checked by TaskEvidenceService so the rejection carries its reason.
     */
    public function submitEvidence(Account $user, Task $task): bool
    {
        return $task->assignedTo($user);
    }

    /**
     * Admin and Managers for every division, the Supervisor of the task's division, and its active PICs may comment;
     * everyone else reads the discussion through view(). Whether the task is Done is checked by TaskCommentService so
     * the rejection carries its reason.
     */
    public function comment(Account $user, Task $task): bool
    {
        return $task->commentableBy($user);
    }

    /**
     * Only the task's active PICs with task.update may delete evidence, and then only evidence they uploaded
     * themselves; that and the task's status are checked by TaskEvidenceService so the rejection carries its reason.
     * Admin, Managers, and Supervisors who are not its PIC never delete a PIC's evidence.
     */
    public function deleteEvidence(Account $user, Task $task): bool
    {
        return $user->hasPermission(Permission::TASK_UPDATE) && $task->assignedTo($user);
    }
}
