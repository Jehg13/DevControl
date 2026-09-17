<?php

namespace App\Nexus;

use App\Models\User;

final class NexusToolContext
{
    private bool $trustedSystem = false;

    public function __construct(
        public readonly ?User $user = null,
        public readonly string $source = 'application',
        public readonly bool $confirmed = false,
        public readonly bool $system = false,
        public readonly array $grantedPermissions = [],
        public readonly ?int $runId = null,
        public readonly ?int $projectId = null,
        public readonly ?string $toolName = null,
    ) {
    }

    public static function core(string $source = 'core', bool $confirmed = false): self
    {
        $context = new self(null, $source, $confirmed, true);
        $context->trustedSystem = true;

        return $context;
    }

    public function isTrustedSystem(): bool
    {
        return $this->trustedSystem;
    }
}
