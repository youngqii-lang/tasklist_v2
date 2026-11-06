<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskEvidenceRequest;
use App\Http\Resources\TaskEvidenceResource;
use App\Models\Task;
use App\Models\TaskEvidence;
use App\Services\TaskEvidenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskEvidenceController extends Controller
{
    /**
     * List the evidence of a task, newest first.
     */
    public function index(Task $task): AnonymousResourceCollection
    {
        Gate::authorize('view', $task);

        return TaskEvidenceResource::collection($task->evidences()->with('submitter')->latest()->latest('id')->get());
    }

    /**
     * Attach a file or link as evidence of the work.
     */
    public function store(StoreTaskEvidenceRequest $request, Task $task, TaskEvidenceService $taskEvidenceService): JsonResponse
    {
        $evidence = $taskEvidenceService->add($request->user(), $task, $request->validated(), $request->file('file'));

        return (new TaskEvidenceResource($evidence))
            ->additional(['message' => 'Evidence berhasil disimpan.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Download an evidence file after checking access to the task.
     */
    public function download(Task $task, TaskEvidence $evidence, TaskEvidenceService $taskEvidenceService): StreamedResponse
    {
        abort_unless($evidence->task_id === $task->id && $evidence->type === TaskEvidence::TYPE_FILE, 404);

        Gate::authorize('view', $task);

        return $taskEvidenceService->download($evidence);
    }
}
