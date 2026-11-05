<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTaskStatusRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TaskService;
use App\Services\TaskWorkflowService;

class TaskStatusController extends Controller
{
    /**
     * Move a task to another workflow status.
     */
    public function update(
        UpdateTaskStatusRequest $request,
        Task $task,
        TaskWorkflowService $taskWorkflowService,
        TaskService $taskService,
    ): TaskResource {
        $task = $taskWorkflowService->transition(
            $request->user(),
            $task,
            $request->validated('status'),
            $request->integer('version'),
        );

        return (new TaskResource($taskService->loadDetail($task)))->additional(['message' => 'Status tugas berhasil diperbarui.']);
    }
}
