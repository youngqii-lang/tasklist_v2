<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskReviewRequest;
use App\Http\Resources\TaskResource;
use App\Http\Resources\TaskReviewResource;
use App\Models\Task;
use App\Services\TaskService;
use App\Services\TaskWorkflowService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TaskReviewController extends Controller
{
    /**
     * List the review decisions of a task, newest first.
     */
    public function index(Task $task): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        return TaskReviewResource::collection($task->reviews()->with('reviewer')->latest()->latest('id')->get());
    }

    /**
     * Approve the task or request a revision.
     */
    public function store(
        StoreTaskReviewRequest $request,
        Task $task,
        TaskWorkflowService $taskWorkflowService,
        TaskService $taskService,
    ): TaskResource {
        $task = $taskWorkflowService->review(
            $request->user(),
            $task,
            $request->validated('decision'),
            $request->integer('version'),
            $request->validated('note'),
        );

        return (new TaskResource($taskService->loadDetail($task)))->additional(['message' => 'Keputusan review berhasil disimpan.']);
    }
}
