<?php

namespace App\Services;

use App\Exceptions\TaskReviewSettingsLockedException;
use App\Models\Account;
use App\Models\Division;
use App\Models\Platform;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskCategory;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
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

    /**
     * Update the main information of a task and record what changed.
     *
     * The QA requirement follows the category when the task has one, and cannot change once the task is in review or done.
     *
     * @param  array{
     *     version: int,
     *     title?: string,
     *     description?: string|null,
     *     division_id?: int,
     *     category_id?: int|null,
     *     platform_id?: int|null,
     *     priority?: string,
     *     start_date?: string|null,
     *     deadline?: string|null,
     *     requires_review?: bool,
     * }  $data
     */
    public function update(Account $editor, Task $task, array $data): Task
    {
        return DB::transaction(function () use ($editor, $task, $data) {
            $task = $task->lockForVersion((int) $data['version']);

            $task->fill($this->withReviewSettings($task, Arr::except($data, 'version')));

            if ($task->isDirty(['category_id', 'requires_review', 'evidence_required'])
                && in_array($task->status, Task::REVIEW_STAGE_STATUSES, true)) {
                throw new TaskReviewSettingsLockedException;
            }

            $changes = $this->describeChanges($task);

            if ($changes !== []) {
                $task->version++;
                $task->save();
                $task->recordActivity($editor, TaskActivity::ACTION_UPDATED, $changes);
            }

            return $this->loadDetail($task);
        });
    }

    /**
     * Load everything the task detail panel shows.
     */
    public function loadDetail(Task $task): Task
    {
        return $task->load([
            'division',
            'category',
            'platform',
            'creator',
            'pics',
            'evidences' => fn ($query) => $query->with('submitter')->latest()->latest('id'),
            'reviews' => fn ($query) => $query->with('reviewer')->latest()->latest('id'),
            'activities' => fn ($query) => $query->with('account')->latest()->latest('id'),
        ]);
    }

    /**
     * Derive the QA settings from the category, when the task has or gets one.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function withReviewSettings(Task $task, array $attributes): array
    {
        $categoryId = array_key_exists('category_id', $attributes) ? $attributes['category_id'] : $task->category_id;
        $category = $categoryId ? TaskCategory::find($categoryId) : null;

        if ($category !== null) {
            $attributes['requires_review'] = $category->requires_review;
            $attributes['evidence_required'] = $category->evidence_required;
        } elseif (array_key_exists('category_id', $attributes)) {
            $attributes['evidence_required'] = false;
        }

        return $attributes;
    }

    /**
     * Describe the dirty attributes as before and after values for the activity history.
     *
     * @return array<string, array{from: mixed, to: mixed}>
     */
    protected function describeChanges(Task $task): array
    {
        $relations = [
            'division_id' => ['division', Division::class],
            'category_id' => ['category', TaskCategory::class],
            'platform_id' => ['platform', Platform::class],
        ];

        $changes = [];

        foreach (array_keys($task->getDirty()) as $attribute) {
            $from = $task->getOriginal($attribute);
            $to = $task->getAttribute($attribute);

            if (isset($relations[$attribute])) {
                [$name, $model] = $relations[$attribute];
                $changes[$name] = [
                    'from' => $from ? $model::find($from)?->name : null,
                    'to' => $to ? $model::find($to)?->name : null,
                ];

                continue;
            }

            $changes[$attribute] = [
                'from' => $from instanceof CarbonInterface ? $from->toDateString() : $from,
                'to' => $to instanceof CarbonInterface ? $to->toDateString() : $to,
            ];
        }

        return $changes;
    }
}
