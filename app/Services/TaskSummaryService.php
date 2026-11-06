<?php

namespace App\Services;

use App\Models\Division;
use App\Models\Task;

class TaskSummaryService
{
    /**
     * Calculate the monitoring KPIs over every division, narrowed only by the given filters.
     *
     * Overdue, due today and urgent only count unfinished tasks; tasks without a deadline never count as overdue or due today.
     *
     * @param  array{search?: string|null, division_id?: int|null, priority?: string|null, status?: string|null}  $filters
     * @return array{
     *     scope: array{type: string, label: string},
     *     metrics: array<string, int>,
     *     completion_rate: float,
     * }
     */
    public function summarize(array $filters): array
    {
        $today = today()->toDateString();
        $tomorrow = today()->addDay()->toDateString();
        $unfinished = 'status <> ?';

        $row = Task::query()
            ->filter($filters)
            ->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as backlog', [Task::STATUS_BACKLOG])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as todo', [Task::STATUS_TODO])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as in_progress', [Task::STATUS_IN_PROGRESS])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as ready_review', [Task::STATUS_READY_REVIEW])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as on_review', [Task::STATUS_ON_REVIEW])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as completed', [Task::STATUS_DONE])
            ->selectRaw("sum(case when {$unfinished} then 1 else 0 end) as unfinished", [Task::STATUS_DONE])
            ->selectRaw("sum(case when {$unfinished} and deadline < ? then 1 else 0 end) as overdue", [Task::STATUS_DONE, $today])
            ->selectRaw("sum(case when {$unfinished} and deadline >= ? and deadline < ? then 1 else 0 end) as due_today", [Task::STATUS_DONE, $today, $tomorrow])
            ->selectRaw("sum(case when {$unfinished} and priority = ? then 1 else 0 end) as urgent", [Task::STATUS_DONE, Task::PRIORITY_URGENT])
            ->first();

        $metrics = array_map(fn ($value) => (int) $value, (array) $row);

        return [
            'scope' => $this->scope(isset($filters['division_id']) ? (int) $filters['division_id'] : null),
            'metrics' => $metrics,
            'completion_rate' => $metrics['total'] === 0 ? 0.0 : round($metrics['completed'] / $metrics['total'] * 100, 1),
        ];
    }

    /**
     * Describe which data the summary covers, so the dashboard can label it.
     *
     * @return array{type: string, label: string}
     */
    protected function scope(?int $divisionId): array
    {
        if ($divisionId === null) {
            return ['type' => 'all', 'label' => 'Semua Divisi'];
        }

        return ['type' => 'division', 'label' => Division::find($divisionId)?->name ?? 'Divisi'];
    }
}
