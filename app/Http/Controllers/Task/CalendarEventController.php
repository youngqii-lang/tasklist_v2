<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\SaveCalendarEventRequest;
use App\Http\Resources\CalendarEventResource;
use App\Models\CalendarEvent;
use App\Services\CalendarEventService;
use Illuminate\Http\JsonResponse;

class CalendarEventController extends Controller
{
    public function __construct(private CalendarEventService $events) {}

    public function store(SaveCalendarEventRequest $request): JsonResponse
    {
        $event = $this->events->create($request->user(), $request->validated());

        return CalendarEventResource::make($event)
            ->additional(['message' => 'Agenda berhasil ditambahkan.'])
            ->response()
            ->setStatusCode(201);
    }

    public function update(SaveCalendarEventRequest $request, CalendarEvent $calendarEvent): JsonResponse
    {
        $event = $this->events->update($request->user(), $calendarEvent, $request->validated());

        return CalendarEventResource::make($event)
            ->additional(['message' => 'Agenda berhasil diperbarui.'])
            ->response();
    }

    public function destroy(CalendarEvent $calendarEvent): JsonResponse
    {
        $this->events->delete($calendarEvent);

        return response()->json(['message' => 'Agenda berhasil dihapus.']);
    }
}
