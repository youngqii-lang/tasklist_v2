<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A reply must answer a comment of the same task.
 */
class CommentReplyOutsideTaskException extends Exception
{
    public function __construct()
    {
        parent::__construct('Balasan hanya dapat ditujukan ke komentar pada tugas yang sama.');
    }

    public function render(Request $request): JsonResponse|false
    {
        if (! $request->expectsJson() && ! $request->is('api/*')) {
            return false;
        }

        return response()->json([
            'message' => $this->getMessage(),
            'errors' => ['parent_comment_id' => [$this->getMessage()]],
        ], 422);
    }
}
