<?php

namespace App\Http\Resources;

use App\Enums\TaskStatus;
use Illuminate\Http\Request;

/**
 * Summary of one task for the Calendar pop-up: what the Task Card shows plus its description, whether it is overdue,
 * and what the account may do with it. Evidence, history, and reviews stay behind the task detail.
 */
class TaskPreviewResource extends TaskResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...parent::toArray($request),
            'description' => $this->description,
            'is_overdue' => $this->status !== TaskStatus::Done && $this->deadline !== null && $this->deadline->lt(today()),
        ];
    }
}
