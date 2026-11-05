<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Thrown when a comment is added to a task that is already done; its comments stay readable.
 */
class TaskCommentsClosedException extends UnprocessableEntityHttpException
{
    public function __construct()
    {
        parent::__construct('Komentar tidak dapat ditambahkan pada tugas yang sudah selesai.');
    }
}
