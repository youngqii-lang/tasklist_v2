<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Thrown when the category or QA requirement is changed while the task is being reviewed or already done.
 */
class TaskReviewSettingsLockedException extends UnprocessableEntityHttpException
{
    public function __construct()
    {
        parent::__construct('Kategori dan kewajiban QA tidak dapat diubah saat tugas sedang diperiksa atau sudah selesai.');
    }
}
