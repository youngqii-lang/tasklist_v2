<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShowTimelineRequest;
use App\Services\TimelineService;
use Illuminate\Http\JsonResponse;

class TimelineController extends Controller
{
    /**
     * Return the read-only timeline of tasks and shared events for a week or two weeks.
     */
    public function __invoke(ShowTimelineRequest $request, TimelineService $timelineService): JsonResponse
    {
        return response()->json(['data' => $timelineService->build($request->user(), $request->validated())]);
    }
}
