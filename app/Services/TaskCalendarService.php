<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CalendarEvent;
use App\Models\Task;
use App\Repositories\TaskRepository;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class TaskCalendarService
{
    public function __construct(private TaskRepository $tasks) {}

    /**
     * The Calendar of a month, as two separate lists: the agendas the account may see, shared across divisions,
     * and the deadlines of the tasks the account may see (every division for an active account). A division filter narrows both lists and never widens what the account may see.
     *
     * @return array{events: Collection<int, CalendarEvent>, deadlines: Collection<int, Task>}
     */
    public function calendar(Account $account, CarbonInterface $month, ?string $division): array
    {
        $from = $month->copy()->startOfMonth();
        $to = $month->copy()->endOfMonth();

        return [
            'events' => CalendarEvent::query()
                ->with(['division', 'targetDivisions'])
                ->visibleTo($account)
                ->within($from, $to)
                ->forDivision($division)
                ->orderBy('start_at')
                ->orderBy('id')
                ->get(),
            'deadlines' => $this->tasks->deadlinesBetween($account, $from, $to, $division),
        ];
    }
}
