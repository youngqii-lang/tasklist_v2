<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Thrown when evidence is added while the task is being reviewed or already done.
 */
class TaskEvidenceLockedException extends UnprocessableEntityHttpException
{
    public function __construct()
    {
        parent::__construct('Evidence tidak dapat diubah saat tugas sedang diperiksa atau sudah selesai.');
    }
}
