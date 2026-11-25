<?php

namespace App\Exceptions;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Thrown when an account cannot be deleted because tasks, comments or agenda items still refer to it.
 */
class AccountHasTaskHistoryException extends ConflictHttpException
{
    public function __construct()
    {
        parent::__construct('Akun ini tidak dapat dihapus karena masih memiliki riwayat tugas, komentar, atau agenda.');
    }

    /**
     * Send web users back with the reason; JSON requests get the default 409 response.
     */
    public function render(Request $request): ?RedirectResponse
    {
        return $request->expectsJson() ? null : back()->with('error', $this->getMessage());
    }
}
