<?php

namespace App\Contracts;

interface NexusAiTransport
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function send(array $payload): array;
}
