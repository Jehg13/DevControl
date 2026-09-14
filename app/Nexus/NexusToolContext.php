<?php

namespace App\Nexus;

use App\Models\User;

final class NexusToolContext
{
    public function __construct(
        public readonly ?User $user = null,
        public readonly string $source = 'application',
        public readonly bool $confirmed = false,
        public readonly bool $system = false,
    ) {
    }
}
