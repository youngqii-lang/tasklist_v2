<?php

namespace App\Services;

use App\Exceptions\TaskEvidenceLockedException;
use App\Models\Account;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskEvidence;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskEvidenceService
{
    /**
     * Statuses in which the evidence of a task can no longer be changed.
     *
     * @var list<string>
     */
    protected const array LOCKED_STATUSES = [Task::STATUS_ON_REVIEW, Task::STATUS_DONE];

    /**
     * Attach a file or link as evidence. The file is stored on the private evidence disk.
     *
     * @param  array{type: string, url?: string|null, note?: string|null}  $data
     *
     * @throws TaskEvidenceLockedException
     */
    public function add(Account $submitter, Task $task, array $data, ?UploadedFile $file = null): TaskEvidence
    {
        if (in_array($task->status, self::LOCKED_STATUSES, true)) {
            throw new TaskEvidenceLockedException;
        }

        $attributes = [
            'submitter_id' => $submitter->id,
            'type' => $data['type'],
            'note' => $data['note'] ?? null,
        ];

        if ($data['type'] === TaskEvidence::TYPE_FILE) {
            $attributes += [
                'file_path' => $file->store("task-evidences/{$task->id}", $this->disk()),
                'file_name' => Str::limit($file->getClientOriginalName(), 255, ''),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
            ];
        } else {
            $attributes['url'] = $data['url'];
            $attributes['title'] = $data['title'] ?? null;
        }

        return DB::transaction(function () use ($submitter, $task, $attributes) {
            $evidence = $task->evidences()->create($attributes);

            $task->recordActivity($submitter, TaskActivity::ACTION_EVIDENCE_ADDED, [
                'evidence' => ['id' => $evidence->id, 'type' => $evidence->type, 'name' => $evidence->displayName()],
            ]);

            return $evidence->load('submitter');
        });
    }

    /**
     * Remove an evidence the PIC uploaded. It is soft deleted, so the review trail keeps it, and the file stays stored.
     *
     * @throws TaskEvidenceLockedException
     */
    public function delete(Account $actor, Task $task, TaskEvidence $evidence): void
    {
        if (in_array($task->status, self::LOCKED_STATUSES, true)) {
            throw new TaskEvidenceLockedException;
        }

        DB::transaction(function () use ($actor, $task, $evidence) {
            $evidence->update(['deleted_by' => $actor->id]);
            $evidence->delete();

            $task->recordActivity($actor, TaskActivity::ACTION_EVIDENCE_DELETED, [
                'evidence' => ['id' => $evidence->id, 'type' => $evidence->type, 'name' => $evidence->displayName()],
            ]);
        });
    }

    /**
     * Stream an evidence file as a download.
     */
    public function download(TaskEvidence $evidence): StreamedResponse
    {
        return Storage::disk($this->disk())->download($evidence->file_path, $evidence->file_name, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Stream an image or PDF evidence inline for the preview. The stored MIME type is sent and sniffing is disabled.
     */
    public function preview(TaskEvidence $evidence): StreamedResponse
    {
        return Storage::disk($this->disk())->response($evidence->file_path, $evidence->file_name, [
            'Content-Type' => $evidence->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    protected function disk(): string
    {
        return config('tasklist.evidence.disk');
    }
}
