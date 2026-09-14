<?php

namespace App\Nexus;

final class NexusReasoningResult
{
    private function __construct(
        public readonly bool $successful,
        public readonly ?NexusModelResponse $response = null,
        public readonly ?string $error = null,
        public readonly ?string $errorCode = null,
    ) {
    }

    public static function success(NexusModelResponse $response): self
    {
        return new self(true, $response);
    }

    public static function failure(string $code, string $message): self
    {
        return new self(false, null, $message, $code);
    }

    public function toArray(): array
    {
        return [
            'ok' => $this->successful,
            'data' => $this->response?->toArray(),
            'error' => $this->successful ? null : [
                'code' => $this->errorCode,
                'message' => $this->error,
            ],
        ];
    }
}
