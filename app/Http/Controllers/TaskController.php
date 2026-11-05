<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Division;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    /**
     * Store a newly created task and return it as JSON for the frontend.
     */
    public function store(StoreTaskRequest $request, TaskService $taskService): JsonResponse
    {
        Gate::authorize('create', [Task::class, Division::findOrFail($request->integer('division_id'))]);

        $task = $taskService->create($request->user(), $request->validated());

        return (new TaskResource($task))
            ->additional(['message' => 'Tugas berhasil dibuat.'])
            ->response()
            ->setStatusCode(201);
    }
}
