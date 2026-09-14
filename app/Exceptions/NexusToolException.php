<?php

namespace App\Exceptions;

use RuntimeException;

class NexusToolException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'tool_error',
    ) {
        parent::__construct($message);
    }
}
