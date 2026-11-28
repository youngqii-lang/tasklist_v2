<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * A task deadline on the Calendar: the task as on the Board and Table, with its short description, whether its
 * deadline has passed while it is still active, and the task_id the Calendar's agendas also carry (null there).
 */
class CalendarDeadlineResource extends TaskResource
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
            'task_id' => $this->id,
            'description' => $this->description,
        ];
    }
}
