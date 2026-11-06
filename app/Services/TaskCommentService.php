<?php

namespace App\Services;

use App\Exceptions\TaskCommentsClosedException;
use App\Models\Account;
use App\Models\Task;
use App\Models\TaskComment;
use Illuminate\Database\Eloquent\Collection;

class TaskCommentService
{
    /**
     * Get the comment threads of a task: top-level comments with their replies, oldest first.
     *
     * @return Collection<int, TaskComment>
     */
    public function threads(Task $task): Collection
    {
        return $task->comments()
            ->whereNull('parent_comment_id')
            ->with(['author', 'replies' => fn ($query) => $query->with('author')->oldest()->oldest('id')])
            ->oldest()
            ->oldest('id')
            ->get();
    }

    /**
     * Add a comment or reply. Replies always attach to the top-level comment so a thread stays one level deep.
     *
     * Comments never change the task status and cannot be added once the task is done.
     *
     * @throws TaskCommentsClosedException
     */
    public function add(Account $author, Task $task, string $content, ?int $parentId = null): TaskComment
    {
        if ($task->status === Task::STATUS_DONE) {
            throw new TaskCommentsClosedException;
        }

        $parent = $parentId === null ? null : $task->comments()->findOrFail($parentId);

        return $task->comments()->create([
            'author_id' => $author->id,
            'author_role' => $author->role?->name,
            'parent_comment_id' => $parent?->parent_comment_id ?? $parent?->id,
            'content' => $content,
        ])->load('author');
    }
}
