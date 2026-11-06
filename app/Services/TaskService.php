<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Task;
use App\Models\TaskCategory;
use Illuminate\Support\Facades\DB;

class TaskService
{
    /**
     * Create a task and assign its PICs in one transaction.
     *
     * The QA requirement follows the category when one is chosen; otherwise the submitted value is used.
     *
     * @param  array{
     *     title: string,
     *     description?: string|null,
     *     division_id: int,
     *     category_id?: int|null,
     *     platform_id?: int|null,
     *     priority?: string|null,
     *     status?: string|null,
     *     start_date?: string|null,
     *     deadline?: string|null,
     *     requires_review?: bool|null,
     *     pic_ids?: list<int>|null,
     * }  $data
     */
    public function create(Account $creator, array $data): Task
    {
        $category = isset($data['category_id']) ? TaskCategory::find($data['category_id']) : null;

        return DB::transaction(function () use ($creator, $data, $category) {
            $task = Task::create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'division_id' => $data['division_id'],
                'category_id' => $category?->id,
                'platform_id' => $data['platform_id'] ?? null,
                'creator_id' => $creator->id,
                'priority' => $data['priority'] ?? Task::PRIORITY_MEDIUM,
                'status' => $data['status'] ?? Task::STATUS_BACKLOG,
                'start_date' => $data['start_date'] ?? null,
                'deadline' => $data['deadline'] ?? null,
                'requires_review' => $category?->requires_review ?? (bool) ($data['requires_review'] ?? false),
                'evidence_required' => $category?->evidence_required ?? false,
            ]);

            $assignedAt = now();

            foreach ($data['pic_ids'] ?? [] as $picId) {
                $task->assignments()->create([
                    'account_id' => $picId,
                    'assigned_by' => $creator->id,
                    'assigned_at' => $assignedAt,
                ]);
            }

            return $task->load(['division', 'category', 'platform', 'creator', 'pics']);
        });
    }
}
