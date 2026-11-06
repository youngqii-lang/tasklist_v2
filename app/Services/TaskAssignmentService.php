<?php

namespace App\Services;

use App\Exceptions\TaskAssignmentLockedException;
use App\Models\Account;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskAssignment;
use Illuminate\Support\Facades\DB;

class TaskAssignmentService
{
    /**
     * Replace the PICs of a task. Removed PICs keep their assignment row with an end time, so the history stays complete.
     *
     * The task status is never changed here.
     *
     * @param  list<int>  $picIds
     */
    public function syncPics(Account $actor, Task $task, array $picIds, int $version, ?string $note = null): Task
    {
        return DB::transaction(function () use ($actor, $task, $picIds, $version, $note) {
            $task = $task->lockForVersion($version);

            if (in_array($task->status, Task::REVIEW_STAGE_STATUSES, true)) {
                throw new TaskAssignmentLockedException;
            }

            $activeAssignments = $task->assignments()->active()->with('account')->get();
            $currentIds = $activeAssignments->pluck('account_id')->all();
            $removedIds = array_values(array_diff($currentIds, $picIds));
            $addedIds = array_values(array_diff($picIds, $currentIds));

            if ($removedIds === [] && $addedIds === []) {
                return $task;
            }

            $now = now();

            $activeAssignments
                ->whereIn('account_id', $removedIds)
                ->each(fn (TaskAssignment $assignment) => $assignment->update([
                    'unassigned_by' => $actor->id,
                    'unassigned_at' => $now,
                    'note' => $note,
                ]));

            foreach ($addedIds as $picId) {
                $task->assignments()->create([
                    'account_id' => $picId,
                    'assigned_by' => $actor->id,
                    'assigned_at' => $now,
                    'note' => $note,
                ]);
            }

            $task->version++;
            $task->save();

            $task->recordActivity($actor, TaskActivity::ACTION_PIC_CHANGED, [
                'pics' => [
                    'from' => $this->describePics($activeAssignments->pluck('account')->all()),
                    'to' => $this->describePics($task->pics()->get()->all()),
                ],
                'note' => $note,
            ]);

            return $task;
        });
    }

    /**
     * @param  list<Account>  $accounts
     * @return list<array{id: int, fullname: string}>
     */
    protected function describePics(array $accounts): array
    {
        return array_map(fn (Account $account) => ['id' => $account->id, 'fullname' => $account->fullname], $accounts);
    }
}
