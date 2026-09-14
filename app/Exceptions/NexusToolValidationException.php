<?php

namespace App\Exceptions;

class NexusToolValidationException extends NexusToolException
{
    public function __construct(
        string $message,
        public readonly array $errors = [],
    ) {
        parent::__construct($message, 'validation_failed');
    }
}
