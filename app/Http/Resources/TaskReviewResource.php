<?php

namespace App\Http\Resources;

use App\Models\TaskReview;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskReview
 */
class TaskReviewResource extends JsonResource
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
            'decision' => $this->decision,
            'decision_label' => TaskReview::DECISION_LABELS[$this->decision] ?? $this->decision,
            'note' => $this->note,
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? [
                'id' => $this->reviewer->id,
                'fullname' => $this->reviewer->fullname,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
