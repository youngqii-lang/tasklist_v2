<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A comment or reply. content is returned as written; the frontend shows it as text, never as HTML.
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
            'author' => $this->author ? [
                'id' => $this->author->id,
                'fullname' => $this->author->fullname,
                'role' => $this->author->role ? [
                    'code_role' => $this->author->role->code_role,
                    'name' => $this->author->role->name,
                ] : null,
            ] : null,
            'reply_to' => $this->parent_comment_id === null ? null : [
                'id' => $this->parent_comment_id,
                'author' => $this->parent?->author ? [
                    'id' => $this->parent->author->id,
                    'fullname' => $this->parent->author->fullname,
                ] : null,
            ],
            'created_at' => $this->created_at?->toIso8601ZuluString(),
        ];
    }
}
