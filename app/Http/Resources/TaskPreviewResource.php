<?php

namespace App\Http\Resources;

use App\Models\Account;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The short task summary shown in the calendar pop-up.
 *
 * @mixin Task
 */
class TaskPreviewResource extends JsonResource
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
            'start_date' => $this->start_date?->toDateString(),
            'deadline' => $this->deadline?->toDateString(),
            'pics' => $this->pics->map(fn (Account $pic) => ['id' => $pic->id, 'fullname' => $pic->fullname])->all(),
            'status' => $this->status,
            'status_label' => Task::STATUS_LABELS[$this->status] ?? $this->status,
            'is_overdue' => $this->isOverdue(),
            'board' => [
                'task_id' => $this->id,
                'filters' => [
                    'division_id' => $this->division_id,
                    'status' => $this->status,
                ],
            ],
        ];
    }
}
