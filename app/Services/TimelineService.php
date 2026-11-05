<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CalendarEvent;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class TimelineService
{
    /**
     * Number of days shown per timeline mode.
     *
     * @var array<string, int>
     */
    public const array MODE_DAYS = ['weekly' => 7, 'biweekly' => 14];

    /**
     * Build the shared, read-only timeline of tasks and calendar events across divisions.
     *
     * A task with only a start date or only a deadline is shown on that single day; a task without either is not shown.
     * Task items carry no internal details; `can_view_detail` tells whether the user may open the task.
     *
     * @param  array{start_date?: string|null, mode?: string|null, division_id?: int|string|null}  $filters
     * @return array{period: array{mode: string, from: string, to: string}, tasks: list<array<string, mixed>>, events: list<array<string, mixed>>}
     */
    public function build(Account $viewer, array $filters): array
    {
        $mode = $filters['mode'] ?? 'weekly';
        $from = isset($filters['start_date'])
            ? CarbonImmutable::parse($filters['start_date'])
            : CarbonImmutable::today()->startOfWeek();
        $to = $from->addDays(self::MODE_DAYS[$mode] - 1);
        $divisionId = isset($filters['division_id']) ? (int) $filters['division_id'] : null;

        return [
            'period' => ['mode' => $mode, 'from' => $from->toDateString(), 'to' => $to->toDateString()],
            'tasks' => $this->tasks($viewer, $from, $to, $divisionId),
            'events' => $this->events($from, $to, $divisionId),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function tasks(Account $viewer, CarbonImmutable $from, CarbonImmutable $to, ?int $divisionId): array
    {
        return Task::query()
            ->with(['division', 'pics'])
            ->when($divisionId, fn (Builder $query) => $query->where('division_id', $divisionId))
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->whereNotNull('start_date')->whereDate('start_date', '<=', $to))
                ->orWhere(fn (Builder $query) => $query->whereNull('start_date')->whereDate('deadline', '<=', $to)))
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->whereNotNull('deadline')->whereDate('deadline', '>=', $from))
                ->orWhere(fn (Builder $query) => $query->whereNull('deadline')->whereDate('start_date', '>=', $from)))
            ->orderByRaw('coalesce(start_date, deadline)')
            ->orderBy('id')
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'division' => ['id' => $task->division->id, 'name' => $task->division->name],
                'start' => ($task->start_date ?? $task->deadline)->toDateString(),
                'end' => ($task->deadline ?? $task->start_date)->toDateString(),
                'status' => $task->status,
                'status_label' => Task::STATUS_LABELS[$task->status],
                'can_view_detail' => Gate::forUser($viewer)->allows('view', $task),
            ])
            ->all();
    }

    /**
     * Calendar events of the chosen division plus company-wide events.
     *
     * @return list<array<string, mixed>>
     */
    protected function events(CarbonImmutable $from, CarbonImmutable $to, ?int $divisionId): array
    {
        return CalendarEvent::query()
            ->with('division')
            ->sharedWith($divisionId)
            ->overlapping($from->startOfDay(), $to->endOfDay())
            ->orderBy('start_at')
            ->get()
            ->map(fn (CalendarEvent $event) => $event->toSharedArray())
            ->all();
    }
}
