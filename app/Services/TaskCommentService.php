<?php

namespace App\Services;

use App\Enums\TaskStatus;
use App\Exceptions\CommentReplyOutsideTaskException;
use App\Models\Account;
use App\Models\Task;
use App\Models\TaskActivity;
use App\Models\TaskComment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskCommentService
{
    private const RELATIONS = ['author.role', 'parent.author'];

    /**
     * Every comment and reply of the task, oldest first, with who wrote it and which comment it answers.
     *
     * @return Collection<int, TaskComment>
     */
    public function list(Task $task): Collection
    {
        return $task->comments()->with(self::RELATIONS)->get();
    }

    /**
     * Add a comment, or a reply when parent_comment_id is given, and record it in the task's history. The task is
     * read again under lock, so who may comment and its status are checked on the latest data. A request sent again
     * with the same client_request_id returns the comment it already stored; a new client_request_id always adds a
     * new comment, even with the same text. A comment never changes the task's status, review, or PICs.
     *
     * @param  array{content: string, client_request_id: string, parent_comment_id?: ?int}  $data
     *
     * @throws AuthorizationException When the account no longer takes part in the task's discussion.
     * @throws ValidationException When the task is Done, or the client_request_id belongs to a comment on another task.
     * @throws CommentReplyOutsideTaskException When the parent comment is not a comment of this task.
     */
    public function store(Task $task, Account $author, array $data): TaskComment
    {
        try {
            return DB::transaction(fn () => $this->create($task, $author, $data));
        } catch (UniqueConstraintViolationException) {
            // The same ID was stored by a request that ran at the same time; answer with what it stored.
            return $this->sent($task, $author, $data['client_request_id'])
                ?? throw ValidationException::withMessages(['client_request_id' => 'Komentar gagal dikirim. Coba lagi.']);
        }
    }

    /**
     * @param  array{content: string, client_request_id: string, parent_comment_id?: ?int}  $data
     */
    private function create(Task $task, Account $author, array $data): TaskComment
    {
        $locked = Task::query()->whereKey($task->id)->lockForUpdate()->firstOrFail();

        if (! $locked->commentableBy($author)) {
            throw new AuthorizationException;
        }

        if ($sent = $this->sent($locked, $author, $data['client_request_id'])) {
            return $sent;
        }

        if ($locked->status === TaskStatus::Done) {
            throw ValidationException::withMessages([
                'content' => 'Tugas yang sudah selesai tidak dapat dikomentari.',
            ]);
        }

        $parentId = isset($data['parent_comment_id']) ? (int) $data['parent_comment_id'] : null;

        if ($parentId !== null && ! $locked->comments()->whereKey($parentId)->exists()) {
            throw new CommentReplyOutsideTaskException;
        }

        $comment = $locked->comments()->create([
            'author_id' => $author->id,
            'parent_comment_id' => $parentId,
            'content' => $data['content'],
            'client_request_id' => $data['client_request_id'],
        ]);

        $locked->activities()->create([
            'account_id' => $author->id,
            'action' => TaskActivity::COMMENT_ADDED,
            'changes' => ['comment' => [
                'old' => null,
                'new' => ['id' => $comment->id, 'parent_comment_id' => $parentId],
            ]],
        ]);

        return $comment->load(self::RELATIONS);
    }

    /**
     * The comment this account already stored with the given client_request_id, or null. An ID is the account's own
     * and names one send; reusing it for another task is rejected so it cannot return another task's comment.
     *
     * @throws ValidationException
     */
    private function sent(Task $task, Account $author, string $requestId): ?TaskComment
    {
        $comment = TaskComment::withTrashed()
            ->where('author_id', $author->id)
            ->where('client_request_id', $requestId)
            ->first();

        if ($comment === null) {
            return null;
        }

        if ($comment->task_id !== $task->id) {
            throw ValidationException::withMessages([
                'client_request_id' => 'ID pengiriman ini sudah dipakai untuk komentar lain. Muat ulang halaman lalu coba lagi.',
            ]);
        }

        return $comment->load(self::RELATIONS);
    }
}
