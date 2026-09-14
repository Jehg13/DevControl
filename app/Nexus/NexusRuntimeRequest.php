<?php

namespace App\Nexus;

use App\Models\User;

final class NexusRuntimeRequest
{
    public function __construct(
        public readonly string $message,
        public readonly ?User $user = null,
        public readonly ?int $projectId = null,
        public readonly mixed $project = null,
        public readonly array $conversation = [],
        public readonly array $context = [],
        public readonly array $history = [],
        public readonly array $metadata = [],
    ) {
    }
}
