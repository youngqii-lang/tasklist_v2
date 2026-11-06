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
     * Stream an evidence file as a download.
     */
    public function download(TaskEvidence $evidence): StreamedResponse
    {
        return Storage::disk($this->disk())->download($evidence->file_path, $evidence->file_name);
    }

    protected function disk(): string
    {
        return config('tasklist.evidence.disk');
    }
}
