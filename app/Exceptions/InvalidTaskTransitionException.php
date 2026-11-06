<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Thrown when a status change breaks the task workflow.
 */
class InvalidTaskTransitionException extends UnprocessableEntityHttpException
{
    public function __construct(string $reason)
    {
        parent::__construct($reason);
    }
}
