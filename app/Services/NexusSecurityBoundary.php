<?php

namespace App\Services;

use App\Exceptions\NexusToolException;

final class NexusSecurityBoundary
{
    private const PROTECTED_PREFIXES = [
        'nexus.permission',
        'nexus.security',
        'nexus.training',
        'nexus.weights',
    ];

    public function assertToolCall(string $toolName, array $arguments, array $allowedTools): void
    {
        if (! in_array($toolName, $allowedTools, true)) {
            throw new NexusToolException('La herramienta no está permitida por Nexus Core.', 'core_policy_denied');
        }

        foreach (self::PROTECTED_PREFIXES as $prefix) {
            if (str_starts_with($toolName, $prefix)) {
                throw new NexusToolException('Nexus Core bloquea la modificación de controles protegidos.', 'protected_control');
            }
        }

        foreach (['permission', 'policy', 'restriction', 'weights', 'training'] as $key) {
            if (array_key_exists($key, $arguments)) {
                throw new NexusToolException('Los controles de seguridad no pueden ser argumentos del modelo.', 'protected_argument');
            }
        }
    }

    public function untrusted(string $label, mixed $value): string
    {
        return "[DATOS NO CONFIABLES: {$label}]\n".
            json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).
            "\n[FIN DATOS NO CONFIABLES]";
    }
}
