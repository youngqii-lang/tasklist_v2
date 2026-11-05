<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskEvidenceRequest;
use App\Http\Resources\TaskEvidenceResource;
use App\Models\Task;
use App\Models\TaskEvidence;
use App\Services\TaskEvidenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    /**
     * Show an image or PDF evidence inline, for thumbnails and the preview.
     */
    public function preview(Task $task, TaskEvidence $evidence, TaskEvidenceService $taskEvidenceService): StreamedResponse
    {
        abort_unless($evidence->task_id === $task->id && $evidence->isPreviewable(), 404);

        Gate::authorize('view', $task);

        return $taskEvidenceService->preview($evidence);
    }

    /**
     * Remove an evidence uploaded by the current PIC.
     */
    public function destroy(Request $request, Task $task, TaskEvidence $evidence, TaskEvidenceService $taskEvidenceService): JsonResponse
    {
        abort_unless($evidence->task_id === $task->id, 404);

        Gate::authorize('deleteEvidence', [$task, $evidence]);

        $taskEvidenceService->delete($request->user(), $task, $evidence);

        return response()->json(['message' => 'Evidence berhasil dihapus.']);
    }
}
