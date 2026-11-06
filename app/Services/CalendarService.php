<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CalendarEvent;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CalendarService
{
    /**
     * Build the monthly calendar: the shared events of every division and, per date, how many visible task deadlines fall on it.
     *
     * @return array{
     *     month: string,
     *     events: list<array<string, mixed>>,
     *     deadlines: list<array{date: string, total: int, overdue: int}>,
     * }
     */
    public function month(Account $viewer, CarbonImmutable $month, ?int $divisionId): array
    {
        $from = $month->startOfMonth();
        $to = $month->endOfMonth();

        $deadlines = $this->visibleTasks($viewer, $divisionId)
            ->whereDate('deadline', '>=', $from)
            ->whereDate('deadline', '<=', $to)
            ->toBase()
            ->select('deadline')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status <> ? and deadline < ? then 1 else 0 end) as overdue', [
                Task::STATUS_DONE,
                today()->toDateString(),
            ])
            ->groupBy('deadline')
            ->orderBy('deadline')
            ->get()
            ->map(fn (object $row) => [
                'date' => CarbonImmutable::parse($row->deadline)->toDateString(),
                'total' => (int) $row->total,
                'overdue' => (int) $row->overdue,
            ])
            ->all();

        return [
            'month' => $month->format('Y-m'),
            'events' => $this->events($from->startOfDay(), $to->endOfDay(), $divisionId),
            'deadlines' => $deadlines,
        ];
    }

    /**
     * Get the shared events of a single date.
     *
     * @return list<array<string, mixed>>
     */
    public function eventsOn(CarbonImmutable $date, ?int $divisionId): array
    {
        return $this->events($date->startOfDay(), $date->endOfDay(), $divisionId);
    }

    /**
     * Get the tasks due on a date that the viewer may see.
     *
     * @return Collection<int, Task>
     */
    public function deadlinesOn(Account $viewer, CarbonImmutable $date, ?int $divisionId): Collection
    {
        return $this->visibleTasks($viewer, $divisionId)
            ->whereDate('deadline', $date)
            ->with(['division', 'pics'])
            ->sortBy('priority', 'desc')
            ->get();
    }

    /**
     * Deadlines follow the task access rules; the division filter can only narrow them.
     *
     * @return Builder<Task>
     */
    protected function visibleTasks(Account $viewer, ?int $divisionId): Builder
    {
        return Task::query()
            ->visibleTo($viewer)
            ->when($divisionId, fn (Builder $query) => $query->where('division_id', $divisionId));
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function events(CarbonImmutable $from, CarbonImmutable $to, ?int $divisionId): array
    {
        return CalendarEvent::query()
            ->with('division')
            ->sharedWith($divisionId)
            ->overlapping($from, $to)
            ->orderBy('start_at')
            ->get()
            ->map(fn (CalendarEvent $event) => $event->toSharedArray())
            ->all();
    }
}
