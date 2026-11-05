<?php

namespace App\Http\Resources;

use App\Models\TaskEvidence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskEvidence
 */
class TaskEvidenceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isFile = $this->type === TaskEvidence::TYPE_FILE;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->displayName(),
            'title' => $this->title,
            'file_name' => $this->file_name,
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'is_image' => $this->isImage(),
            'url' => $this->url,
            'download_url' => $isFile ? route('api.tasks.evidences.download', [$this->task_id, $this->id]) : null,
            'preview_url' => $this->isPreviewable() ? route('api.tasks.evidences.preview', [$this->task_id, $this->id]) : null,
            'can_delete' => (bool) $request->user()?->can('deleteEvidence', [$this->task, $this->resource]),
            'note' => $this->note,
            'submitter' => $this->whenLoaded('submitter', fn () => $this->submitter ? [
                'id' => $this->submitter->id,
                'fullname' => $this->submitter->fullname,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
