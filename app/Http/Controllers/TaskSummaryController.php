<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListTasksRequest;
use App\Services\TaskSummaryService;
use Illuminate\Http\JsonResponse;

class TaskSummaryController extends Controller
{
    /**
     * Return the monitoring KPIs for the active Tasklist filters.
     */
    public function __invoke(ListTasksRequest $request, TaskSummaryService $taskSummaryService): JsonResponse
    {
        return response()->json(['data' => $taskSummaryService->summarize($request->validated())]);
    }
}
