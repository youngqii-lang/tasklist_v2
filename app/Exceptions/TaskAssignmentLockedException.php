<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Thrown when the PIC of a task is changed while the task is being reviewed or already done.
 */
class TaskAssignmentLockedException extends UnprocessableEntityHttpException
{
    public function __construct()
    {
        parent::__construct('PIC tidak dapat diganti saat tugas sedang diperiksa atau sudah selesai.');
    }
}
