<?php

namespace App\Contracts;

interface NexusCodeParser
{
    public function language(): string;

    public function supports(string $path, string $content): bool;

    /** @return array<string, mixed> */
    public function parse(string $path, string $content): array;
}
