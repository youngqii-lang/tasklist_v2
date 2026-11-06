<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Thrown when a change is based on a version of the task that has since been modified.
 */
class StaleTaskVersionException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('Tugas sudah diperbarui oleh pengguna lain. Muat ulang data lalu coba lagi.');
    }
}
