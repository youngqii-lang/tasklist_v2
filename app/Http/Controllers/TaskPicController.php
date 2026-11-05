<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateTaskPicsRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TaskAssignmentService;
use App\Services\TaskService;

class TaskPicController extends Controller
{
    /**
     * Assign, replace or forward the PICs of a task.
     */
    public function update(
        UpdateTaskPicsRequest $request,
        Task $task,
        TaskAssignmentService $taskAssignmentService,
        TaskService $taskService,
    ): TaskResource {
        $task = $taskAssignmentService->syncPics(
            $request->user(),
            $task,
            array_map('intval', $request->validated('pic_ids')),
            $request->integer('version'),
            $request->validated('note'),
        );

        return (new TaskResource($taskService->loadDetail($task)))->additional(['message' => 'PIC tugas berhasil diperbarui.']);
    }
}
