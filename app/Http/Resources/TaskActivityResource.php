<?php

namespace App\Http\Resources;

use App\Models\TaskActivity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskActivity
 */
class TaskActivityResource extends JsonResource
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
            'action' => $this->action,
            'label' => TaskActivity::ACTION_LABELS[$this->action] ?? $this->action,
            'account' => $this->whenLoaded('account', fn () => $this->account ? [
                'id' => $this->account->id,
                'fullname' => $this->account->fullname,
            ] : null),
            'changes' => $this->changes ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
