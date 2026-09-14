<?php

namespace App\Nexus;

final class NexusToolResult
{
    private function __construct(
        public readonly bool $successful,
        public readonly mixed $data = null,
        public readonly ?string $error = null,
        public readonly ?string $errorCode = null,
        public readonly array $meta = [],
    ) {
    }

    public static function success(mixed $data = null, array $meta = []): self
    {
        return new self(true, $data, null, null, $meta);
    }

    public static function failure(string $errorCode, string $error, array $meta = []): self
    {
        return new self(false, null, $error, $errorCode, $meta);
    }

    public function toArray(): array
    {
        return [
            'ok' => $this->successful,
            'data' => $this->data,
            'error' => $this->successful ? null : [
                'code' => $this->errorCode,
                'message' => $this->error,
            ],
            'meta' => $this->meta,
        ];
    }
}
