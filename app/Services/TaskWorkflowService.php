<?php

namespace App\Services;

use App\Exceptions\InvalidTaskTransitionException;
use App\Models\Account;
use App\Models\Task;
use App\Models\TaskActivity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class TaskWorkflowService
{
    /**
     * The transition may be done by an admin or the manager of the task's division.
     */
    protected const string BY_MANAGER = 'manager';

    /**
     * The transition may be done by a PIC of the task.
     */
    protected const string BY_PIC = 'pic';

    /**
     * Allowed transitions: from status => [to status => who may do it].
     *
     * @var array<string, array<string, string>>
     */
    protected const array TRANSITIONS = [
        Task::STATUS_BACKLOG => [Task::STATUS_TODO => self::BY_MANAGER],
        Task::STATUS_TODO => [Task::STATUS_IN_PROGRESS => self::BY_PIC],
        Task::STATUS_IN_PROGRESS => [Task::STATUS_READY_REVIEW => self::BY_PIC, Task::STATUS_DONE => self::BY_PIC],
        Task::STATUS_READY_REVIEW => [Task::STATUS_ON_REVIEW => self::BY_MANAGER],
        Task::STATUS_ON_REVIEW => [Task::STATUS_DONE => self::BY_MANAGER, Task::STATUS_IN_PROGRESS => self::BY_MANAGER],
    ];

    /**
     * Move a task to another status, following the transition matrix and the QA rules.
     *
     * @throws AuthorizationException
     * @throws InvalidTaskTransitionException
     */
    public function transition(Account $actor, Task $task, string $toStatus, int $version): Task
    {
        return DB::transaction(function () use ($actor, $task, $toStatus, $version) {
            $task = $task->lockForVersion($version);
            $fromStatus = $task->status;
            $allowedBy = self::TRANSITIONS[$fromStatus][$toStatus] ?? null;

            if ($allowedBy === null) {
                throw new InvalidTaskTransitionException(sprintf(
                    'Status tidak dapat dipindahkan dari %s ke %s.',
                    Task::STATUS_LABELS[$fromStatus],
                    Task::STATUS_LABELS[$toStatus],
                ));
            }

            if (! $this->isAllowedBy($actor, $task, $allowedBy)) {
                throw new AuthorizationException($allowedBy === self::BY_PIC
                    ? 'Hanya PIC tugas yang dapat melakukan perpindahan status ini.'
                    : 'Hanya Admin atau Manajer divisi yang dapat melakukan perpindahan status ini.');
            }

            $this->ensureRequirementsAreMet($task, $toStatus);

            $task->status = $toStatus;
            $task->completed_at = $toStatus === Task::STATUS_DONE ? now() : null;
            $task->version++;
            $task->save();

            $task->recordActivity($actor, TaskActivity::ACTION_STATUS_CHANGED, [
                'status' => ['from' => $fromStatus, 'to' => $toStatus],
            ]);

            return $task;
        });
    }

    /**
     * Get the statuses the account may move the task to, for the drag-and-drop board.
     *
     * @return list<string>
     */
    public function availableTransitions(Account $actor, Task $task): array
    {
        return collect(self::TRANSITIONS[$task->status] ?? [])
            ->filter(fn (string $allowedBy, string $toStatus) => $this->isAllowedBy($actor, $task, $allowedBy)
                && $this->reviewRuleAllows($task, $toStatus))
            ->keys()
            ->all();
    }

    /**
     * Determine whether the account holds the role the transition requires.
     */
    protected function isAllowedBy(Account $actor, Task $task, string $allowedBy): bool
    {
        return match ($allowedBy) {
            self::BY_PIC => $task->hasActivePic($actor),
            self::BY_MANAGER => Gate::forUser($actor)->allows('update', $task),
        };
    }

    /**
     * Tasks with QA must go through Ready for QA; tasks without QA go straight to Done.
     */
    protected function reviewRuleAllows(Task $task, string $toStatus): bool
    {
        if ($task->status !== Task::STATUS_IN_PROGRESS) {
            return true;
        }

        return $toStatus === Task::STATUS_READY_REVIEW ? $task->requires_review : ! $task->requires_review;
    }

    /**
     * @throws InvalidTaskTransitionException
     */
    protected function ensureRequirementsAreMet(Task $task, string $toStatus): void
    {
        if (! $this->reviewRuleAllows($task, $toStatus)) {
            throw new InvalidTaskTransitionException($task->requires_review
                ? 'Tugas ini wajib QA, kirim ke Ready for QA terlebih dahulu.'
                : 'Tugas ini tidak wajib QA, selesaikan langsung ke Done.');
        }

        if ($toStatus === Task::STATUS_TODO && ($task->deadline === null || ! $task->pics()->exists())) {
            throw new InvalidTaskTransitionException('Tugas wajib memiliki PIC dan deadline sebelum dipindahkan ke To Do.');
        }
    }
}
