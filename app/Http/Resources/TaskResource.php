<?php

namespace App\Http\Resources;

use App\Models\Account;
use App\Models\Task;
use App\Services\TaskWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'status_label' => Task::STATUS_LABELS[$this->status] ?? $this->status,
            'priority' => $this->priority,
            'priority_label' => Task::PRIORITY_LABELS[$this->priority] ?? $this->priority,
            'start_date' => $this->start_date?->toDateString(),
            'deadline' => $this->deadline?->toDateString(),
            'is_overdue' => $this->isOverdue(),
            'requires_review' => $this->requires_review,
            'evidence_required' => $this->evidence_required,
            'division' => $this->whenLoaded('division', fn () => [
                'id' => $this->division->id,
                'name' => $this->division->name,
            ]),
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'platform' => $this->whenLoaded('platform', fn () => [
                'id' => $this->platform->id,
                'name' => $this->platform->name,
            ]),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'fullname' => $this->creator->fullname,
            ]),
            'pics' => $this->whenLoaded('pics', fn () => $this->pics->map(fn (Account $pic) => [
                'id' => $pic->id,
                'fullname' => $pic->fullname,
                'username' => $pic->username,
            ])->all()),
            'evidences' => TaskEvidenceResource::collection($this->whenLoaded('evidences')),
            'reviews' => TaskReviewResource::collection($this->whenLoaded('reviews')),
            'activities' => TaskActivityResource::collection($this->whenLoaded('activities')),
            'version' => $this->version,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'abilities' => [
                'update' => (bool) $request->user()?->can('update', $this->resource),
                'assign' => (bool) $request->user()?->can('assign', $this->resource),
                'add_evidence' => (bool) $request->user()?->can('addEvidence', $this->resource),
                'review' => (bool) $request->user()?->can('review', $this->resource),
                'transitions' => $request->user()
                    ? app(TaskWorkflowService::class)->availableTransitions($request->user(), $this->resource)
                    : [],
            ],
        ];
    }
}
