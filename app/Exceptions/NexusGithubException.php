<?php

namespace App\Exceptions;

use RuntimeException;

class NexusGithubException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'github_error',
        public readonly ?int $status = null,
        public readonly array $meta = [],
    ) {
        parent::__construct($message);
    }
}
