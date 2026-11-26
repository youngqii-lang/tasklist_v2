<?php

namespace App\Services;

use App\Models\Account;
use App\Models\CalendarEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CalendarEventService
{
    /**
     * Add an agenda. A Supervisor's agenda is always organised by their own division.
     *
     * @param  array{title: string, description?: ?string, start_date: string, end_date?: ?string, is_shared: bool, division_id?: ?int, target_division_ids?: array<int, int>}  $data
     */
    public function create(Account $creator, array $data): CalendarEvent
    {
        return DB::transaction(function () use ($creator, $data) {
            $event = new CalendarEvent(['creator_id' => $creator->id]);
            $this->fill($event, $creator, $data);

            return $event->load(['division', 'targetDivisions']);
        });
    }

    /**
     * Change an agenda's details, dates, and who it is meant for.
     *
     * @param  array{title: string, description?: ?string, start_date: string, end_date?: ?string, is_shared: bool, division_id?: ?int, target_division_ids?: array<int, int>}  $data
     */
    public function update(Account $editor, CalendarEvent $event, array $data): CalendarEvent
    {
        return DB::transaction(function () use ($editor, $event, $data) {
            $this->fill($event, $editor, $data);

            return $event->load(['division', 'targetDivisions']);
        });
    }

    /**
     * Remove an agenda from the schedule. The row is soft deleted so who made it stays on record.
     */
    public function delete(CalendarEvent $event): void
    {
        $event->delete();
    }

    /**
     * @param  array{title: string, description?: ?string, start_date: string, end_date?: ?string, is_shared: bool, division_id?: ?int, target_division_ids?: array<int, int>}  $data
     */
    private function fill(CalendarEvent $event, Account $actor, array $data): void
    {
        $isShared = (bool) $data['is_shared'];
        $endDate = $data['end_date'] ?? null;

        $event->fill([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'start_at' => Carbon::parse($data['start_date'])->startOfDay(),
            // A one-day agenda is stored without an end, as the Calendar and Timeline read it.
            'end_at' => $endDate && $endDate !== $data['start_date'] ? Carbon::parse($endDate)->startOfDay() : null,
            'is_shared' => $isShared,
            'division_id' => $actor->isSupervisor() ? $actor->division_id : ($data['division_id'] ?? null),
        ])->save();

        $event->targetDivisions()->sync($isShared ? [] : array_map('intval', $data['target_division_ids'] ?? []));
    }
}
