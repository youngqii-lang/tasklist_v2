<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Task;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class TaskListService
{
    /**
     * Get one page of the tasks the account may see, filtered and sorted for the Board and Table views.
     *
     * @param  array{search?: string|null, division_id?: int|null, priority?: string|null, status?: string|null, sort?: string|null, direction?: string|null, per_page?: int|null}  $filters
     * @return LengthAwarePaginator<int, Task>
     */
    public function paginate(Account $account, array $filters): LengthAwarePaginator
    {
        return $this->query($account, $filters)
            ->with(['division', 'pics'])
            ->sortBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();
    }

    /**
     * Count the visible, filtered tasks per status. Every status is present, even when it has no tasks.
     *
     * @param  array{search?: string|null, division_id?: int|null, priority?: string|null, status?: string|null}  $filters
     * @return array<string, int>
     */
    public function statusCounts(Account $account, array $filters): array
    {
        $counts = $this->query($account, $filters)
            ->toBase()
            ->select('status')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return collect(Task::STATUS_LABELS)
            ->map(fn (string $label, string $status) => (int) ($counts[$status] ?? 0))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Task>
     */
    protected function query(Account $account, array $filters): Builder
    {
        return Task::query()->visibleTo($account)->filter($filters);
    }
}
