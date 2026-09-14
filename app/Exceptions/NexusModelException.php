<?php

namespace App\Exceptions;

use RuntimeException;

class NexusModelException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'model_error',
    ) {
        parent::__construct($message);
    }
}
