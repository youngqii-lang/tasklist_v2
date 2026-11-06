<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowCalendarDeadlinesRequest;
use App\Http\Requests\ShowCalendarRequest;
use App\Http\Resources\TaskResource;
use App\Services\CalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class CalendarController extends Controller
{
    /**
     * Return the shared events and the deadline markers of a month.
     */
    public function month(ShowCalendarRequest $request, CalendarService $calendarService): JsonResponse
    {
        $month = $request->filled('month')
            ? CarbonImmutable::createFromFormat('Y-m-d', $request->validated('month').'-01')
            : CarbonImmutable::today();

        return response()->json(['data' => $calendarService->month($request->user(), $month, $this->divisionId($request->validated()))]);
    }

    /**
     * Return the shared events and the accessible task deadlines of one date.
     */
    public function deadlines(ShowCalendarDeadlinesRequest $request, CalendarService $calendarService): JsonResponse
    {
        $date = CarbonImmutable::createFromFormat('Y-m-d', $request->validated('date'))->startOfDay();
        $divisionId = $this->divisionId($request->validated());
        $tasks = $calendarService->deadlinesOn($request->user(), $date, $divisionId);

        return response()->json([
            'data' => [
                'date' => $date->toDateString(),
                'events' => $calendarService->eventsOn($date, $divisionId),
                'deadlines' => TaskResource::collection($tasks)->resolve($request),
            ],
            'message' => $tasks->isEmpty() ? 'Tidak ada deadline tugas pada tanggal ini.' : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function divisionId(array $validated): ?int
    {
        return isset($validated['division_id']) ? (int) $validated['division_id'] : null;
    }
}
