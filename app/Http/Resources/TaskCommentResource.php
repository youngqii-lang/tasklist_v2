<?php

namespace App\Http\Resources;

use App\Models\TaskComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TaskComment
 */
class TaskCommentResource extends JsonResource
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
            'task_id' => $this->task_id,
            'parent_comment_id' => $this->parent_comment_id,
            'content' => $this->content,
            'author' => $this->whenLoaded('author', fn () => $this->author ? [
                'id' => $this->author->id,
                'fullname' => $this->author->fullname,
            ] : null),
            'author_role' => $this->author_role,
            'created_at' => $this->created_at?->toIso8601String(),
            'replies' => TaskCommentResource::collection($this->whenLoaded('replies')),
        ];
    }
}
