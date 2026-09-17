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
