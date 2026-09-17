<?php

namespace App\Nexus;

final class NexusToolProposal
{
    public function __construct(
        public readonly string $tool,
        public readonly array $arguments = [],
        public readonly array $permissions = [],
        public readonly bool $requiresConfirmation = false,
        public readonly string $operation = 'read',
        public readonly string $source = 'nexus_ai_python',
        public readonly bool $executable = false,
        public readonly string $correlationId = '',
        public readonly bool $confirmed = false,
        public readonly string $status = 'proposed',
    ) {
    }

    public static function fromArray(array $proposal): self
    {
        $tool = (string) ($proposal['tool'] ?? $proposal['tool_name'] ?? '');
        if ($tool === '') {
            throw new \InvalidArgumentException('La propuesta de herramienta requiere un nombre de herramienta.');
        }

        $arguments = is_array($proposal['arguments'] ?? $proposal['parameters'] ?? null)
            ? $proposal['arguments'] ?? $proposal['parameters']
            : [];

        $permissions = is_array($proposal['permissions'] ?? null)
            ? array_values(array_filter(array_map('strval', $proposal['permissions']), fn (string $permission): bool => $permission !== ''))
            : [];

        $operation = strtolower((string) ($proposal['operation'] ?? 'read'));
        if (! in_array($operation, ['read', 'write', 'delete'], true)) {
            $operation = 'read';
        }

        $source = (string) ($proposal['source'] ?? 'nexus_ai_python');
        $status = (string) ($proposal['status'] ?? 'proposed');
        $correlationId = (string) ($proposal['correlation_id'] ?? $proposal['correlationId'] ?? '');
        $executable = (bool) ($proposal['executable'] ?? false);

        return new self(
            tool: $tool,
            arguments: $arguments,
            permissions: $permissions,
            requiresConfirmation: (bool) ($proposal['requires_confirmation'] ?? false),
            operation: $operation,
            source: $source,
            executable: $executable,
            correlationId: $correlationId,
            confirmed: (bool) ($proposal['confirmed'] ?? false),
            status: $status,
        );
    }

    public function toArray(): array
    {
        return [
            'tool' => $this->tool,
            'arguments' => $this->arguments,
            'permissions' => $this->permissions,
            'requires_confirmation' => $this->requiresConfirmation,
            'operation' => $this->operation,
            'source' => $this->source,
            'executable' => $this->executable,
            'correlation_id' => $this->correlationId,
            'confirmed' => $this->confirmed,
            'status' => $this->status,
        ];
    }
}
