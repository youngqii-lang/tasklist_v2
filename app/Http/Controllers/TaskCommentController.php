<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskCommentRequest;
use App\Http\Resources\TaskCommentResource;
use App\Models\Task;
use App\Services\TaskCommentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class TaskCommentController extends Controller
{
    /**
     * List the comment threads of a task.
     */
    public function index(Task $task, TaskCommentService $taskCommentService): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        return TaskCommentResource::collection($taskCommentService->threads($task));
    }

    /**
     * Add a comment or a reply to a task.
     */
    public function store(StoreTaskCommentRequest $request, Task $task, TaskCommentService $taskCommentService): JsonResponse
    {
        $comment = $taskCommentService->add(
            $request->user(),
            $task,
            $request->validated('content'),
            $request->filled('parent_comment_id') ? $request->integer('parent_comment_id') : null,
        );

        return (new TaskCommentResource($comment))
            ->additional(['message' => 'Komentar berhasil dikirim.'])
            ->response()
            ->setStatusCode(201);
    }
}
