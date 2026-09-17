<?php

namespace App\Exceptions;

use RuntimeException;

class NexusAiTransportException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
