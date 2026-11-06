<?php

namespace App\Http\Controllers;

use App\Http\Requests\DestroyTaskRequest;
use App\Http\Requests\ListTasksRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskPreviewResource;
use App\Http\Resources\TaskResource;
use App\Models\Division;
use App\Models\Task;
use App\Services\TaskListService;
use App\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    /**
     * List the tasks for the Board and Table views, with the per-status counts of the same filters.
     */
    public function index(ListTasksRequest $request, TaskListService $taskListService): AnonymousResourceCollection
    {
        $filters = $request->validated();

        return TaskResource::collection($taskListService->paginate($request->user(), $filters))
            ->additional(['meta' => ['status_counts' => $taskListService->statusCounts($request->user(), $filters)]]);
    }

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

    /**
     * Show the full detail of a task.
     */
    public function show(Task $task, TaskService $taskService): TaskResource
    {
        Gate::authorize('view', $task);

        return new TaskResource($taskService->loadDetail($task));
    }

    /**
     * Show the short summary of a task for the calendar pop-up, using its latest data.
     */
    public function preview(Task $task): TaskPreviewResource
    {
        Gate::authorize('view', $task);

        return new TaskPreviewResource($task->load('pics'));
    }

    /**
     * Update the main information of a task.
     */
    public function update(UpdateTaskRequest $request, Task $task, TaskService $taskService): TaskResource
    {
        $task = $taskService->update($request->user(), $task, $request->validated());

        return (new TaskResource($task))->additional(['message' => 'Tugas berhasil diperbarui.']);
    }

    /**
     * Soft delete a task.
     */
    public function destroy(DestroyTaskRequest $request, Task $task, TaskService $taskService): JsonResponse
    {
        $taskService->delete($request->user(), $task, $request->integer('version'));

        return response()->json(['message' => 'Tugas berhasil dihapus.']);
    }
}
