<?php

namespace App\Services;

use App\Exceptions\InvalidTaskTransitionException;
use App\Models\Account;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskReview;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Status workflow following the TASK-17 transition matrix.
 *
 * A PIC can bring a task at most to Ready Review; reviewing, revising and finishing stay with the
 * admin, manager or supervisor who manages the task.
 */
class TaskWorkflowService
{
    /**
     * The transition may be done by an admin, or a manager or supervisor of the task's division.
     */
    protected const string BY_MANAGER = 'manager';

    /**
     * The transition may be done by a PIC of the task or by its manager.
     */
    protected const string BY_PIC_OR_MANAGER = 'pic_or_manager';

    /**
     * Allowed transitions: from status => [to status => who may do it].
     *
     * @var array<string, array<string, string>>
     */
    protected const array TRANSITIONS = [
        Task::STATUS_BACKLOG => [Task::STATUS_TODO => self::BY_MANAGER],
        Task::STATUS_TODO => [Task::STATUS_IN_PROGRESS => self::BY_PIC_OR_MANAGER],
        Task::STATUS_IN_PROGRESS => [Task::STATUS_READY_REVIEW => self::BY_PIC_OR_MANAGER, Task::STATUS_DONE => self::BY_MANAGER],
        Task::STATUS_READY_REVIEW => [Task::STATUS_ON_REVIEW => self::BY_MANAGER],
        Task::STATUS_ON_REVIEW => [Task::STATUS_DONE => self::BY_MANAGER, Task::STATUS_BACKLOG => self::BY_MANAGER],
    ];

    /**
     * Review decisions made by leaving On Review: to status => decision.
     *
     * @var array<string, string>
     */
    protected const array REVIEW_DECISIONS = [
        Task::STATUS_DONE => TaskReview::DECISION_APPROVED,
        Task::STATUS_BACKLOG => TaskReview::DECISION_REVISION_REQUESTED,
    ];

    /**
     * Record a review decision on a task that is On Review and move it to the matching status.
     *
     * @throws InvalidTaskTransitionException
     */
    public function review(Account $reviewer, Task $task, string $decision, int $version, ?string $note = null): Task
    {
        if ($task->status !== Task::STATUS_ON_REVIEW) {
            throw new InvalidTaskTransitionException('Keputusan review hanya dapat diberikan pada tugas berstatus On Review.');
        }

        return $this->transition($reviewer, $task, array_search($decision, self::REVIEW_DECISIONS, true), $version, $note);
    }

    /**
     * Move a task to another status, following the transition matrix and the review rules.
     *
     * Leaving On Review is a review decision: it is stored in task_reviews, and a revision (back to Back Log) needs a note.
     *
     * @throws AuthorizationException
     * @throws InvalidTaskTransitionException
     * @throws ValidationException
     */
    public function transition(Account $actor, Task $task, string $toStatus, int $version, ?string $note = null): Task
    {
        return DB::transaction(function () use ($actor, $task, $toStatus, $version, $note) {
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
                throw new AuthorizationException($allowedBy === self::BY_PIC_OR_MANAGER
                    ? 'Hanya PIC tugas atau Admin/Manajer/Supervisor yang berwenang yang dapat melakukan perpindahan status ini.'
                    : 'Hanya Admin, Manajer, atau Supervisor yang berwenang yang dapat melakukan perpindahan status ini.');
            }

            $this->ensureRequirementsAreMet($task, $toStatus);

            $decision = $fromStatus === Task::STATUS_ON_REVIEW ? self::REVIEW_DECISIONS[$toStatus] : null;

            if ($decision === TaskReview::DECISION_REVISION_REQUESTED && blank($note)) {
                throw ValidationException::withMessages(['note' => 'Catatan revisi wajib diisi.']);
            }

            $task->status = $toStatus;
            $task->completed_at = $toStatus === Task::STATUS_DONE ? now() : null;
            $task->version++;
            $task->save();

            if ($decision !== null) {
                $task->reviews()->create(['reviewer_id' => $actor->id, 'decision' => $decision, 'note' => $note]);
            }

            $task->recordActivity($actor, $this->activityAction($task, $toStatus, $decision), array_filter([
                'status' => ['from' => $fromStatus, 'to' => $toStatus],
                'note' => $decision !== null ? $note : null,
            ]));

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
     * Name the transition for the activity history: review decisions, (re)submissions, or a plain status change.
     */
    protected function activityAction(Task $task, string $toStatus, ?string $decision): string
    {
        if ($decision !== null) {
            return $decision === TaskReview::DECISION_APPROVED
                ? TaskActivity::ACTION_APPROVED
                : TaskActivity::ACTION_REVISION_REQUESTED;
        }

        if ($toStatus !== Task::STATUS_READY_REVIEW) {
            return TaskActivity::ACTION_STATUS_CHANGED;
        }

        return $task->reviews()->where('decision', TaskReview::DECISION_REVISION_REQUESTED)->exists()
            ? TaskActivity::ACTION_RESUBMITTED
            : TaskActivity::ACTION_SUBMITTED;
    }

    /**
     * Determine whether the account holds the authority the transition requires.
     */
    protected function isAllowedBy(Account $actor, Task $task, string $allowedBy): bool
    {
        $manages = Gate::forUser($actor)->allows('manage', $task);

        return match ($allowedBy) {
            self::BY_PIC_OR_MANAGER => $manages || $task->hasActivePic($actor),
            self::BY_MANAGER => $manages,
        };
    }

    /**
     * Only tasks without a review requirement may be finished straight from On Process.
     */
    protected function reviewRuleAllows(Task $task, string $toStatus): bool
    {
        return ! ($task->status === Task::STATUS_IN_PROGRESS && $toStatus === Task::STATUS_DONE && $task->requires_review);
    }

    /**
     * @throws InvalidTaskTransitionException
     */
    protected function ensureRequirementsAreMet(Task $task, string $toStatus): void
    {
        if (! $this->reviewRuleAllows($task, $toStatus)) {
            throw new InvalidTaskTransitionException('Tugas ini wajib review, kirim ke Ready Review terlebih dahulu.');
        }

        $isSubmission = $task->status === Task::STATUS_IN_PROGRESS
            && in_array($toStatus, [Task::STATUS_READY_REVIEW, Task::STATUS_DONE], true);

        if ($isSubmission && $task->evidence_required && ! $task->evidences()->exists()) {
            throw new InvalidTaskTransitionException('Tugas ini wajib melampirkan evidence sebelum dikirim.');
        }

        if ($toStatus === Task::STATUS_TODO && ($task->deadline === null || ! $task->pics()->exists())) {
            throw new InvalidTaskTransitionException('Tugas wajib memiliki PIC dan deadline sebelum dipindahkan ke To Do.');
        }
    }
}
