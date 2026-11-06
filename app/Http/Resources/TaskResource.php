<?php

namespace App\Http\Resources;

use App\Models\Account;
use App\Models\Task;
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
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
