<?php

namespace App\Services;

use App\Nexus\NexusToolRegistry;

final class NexusCognitiveSecurityBoundary
{
    private const SENSITIVE_TOOLS = [
        'nexus.code.modify',
        'nexus.code.validate',
        'nexus.code.create',
        'nexus.code.delete',
        'nexus.model.promote',
        'nexus.model.approve',
    ];

    public function __construct(private readonly NexusToolRegistry $tools)
    {
    }

    /**
     * Validates model output as untrusted data before it reaches any tool.
     *
     * @param array<int, mixed> $calls
     * @return array<int, string>
     */
    public function validateModelToolCalls(array $calls, bool $confirmed = false): array
    {
        $errors = [];
        foreach ($calls as $index => $call) {
            if (! is_array($call) || ! is_string($call['name'] ?? null) || ! is_array($call['arguments'] ?? null)) {
                $errors[] = "La llamada {$index} tiene una estructura inválida.";
                continue;
            }

            try {
                $tool = $this->tools->get($call['name']);
            } catch (\Throwable) {
                $errors[] = "La herramienta [{$call['name']}] no está autorizada.";
                continue;
            }

            if ($this->containsInstructionLikePayload($call['arguments'])) {
                $errors[] = "Los argumentos de [{$call['name']}] contienen instrucciones no confiables.";
            }
            if (in_array($call['name'], self::SENSITIVE_TOOLS, true)) {
                $errors[] = "La herramienta sensible [{$call['name']}] requiere un plan controlado.";
            }
            if ($tool->requiresConfirmation() && ! $confirmed) {
                $errors[] = "La herramienta [{$call['name']}] requiere confirmación.";
            }
        }
        return $errors;
    }

    public function validateControlledPlan(array $plan, ?int $userId, bool $confirmed): array
    {
        $errors = [];
        if (($plan['status'] ?? null) !== 'ready_for_authorization' || ($plan['executed'] ?? false)) {
            $errors[] = 'El plan no está listo para autorización o ya fue ejecutado.';
        }
        if (! $confirmed) {
            $errors[] = 'El plan requiere confirmación explícita.';
        }
        if (! is_string($plan['plan_id'] ?? null) || trim($plan['plan_id']) === '') {
            $errors[] = 'El plan requiere plan_id.';
        }
        if (isset($plan['plan_hash'])) {
            $copy = $plan;
            unset($copy['plan_hash']);
            $encoded = json_encode($copy, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (! is_string($encoded) || ! hash_equals((string) $plan['plan_hash'], hash('sha256', $encoded))) {
                $errors[] = 'La integridad del plan no pudo verificarse.';
            }
        }
        $declaredTools = $plan['required_tools'] ?? [];
        if (! is_array($declaredTools)) {
            $errors[] = 'required_tools debe ser una lista.';
        } else {
            foreach ($declaredTools as $tool) {
                if (! is_string($tool)) {
                    $errors[] = 'El plan contiene una herramienta inválida.';
                    continue;
                }
                try {
                    $this->tools->get($tool);
                } catch (\Throwable) {
                    $errors[] = "La herramienta [{$tool}] no está registrada.";
                }
            }
        }
        return $errors;
    }

    private function containsInstructionLikePayload(mixed $value): bool
    {
        if (is_string($value)) {
            return preg_match('/ignore\s+(previous|all)\s+instructions|system\s+prompt|grant\s+permission|bypass|execute\s+command/i', $value) === 1;
        }
        if (is_array($value)) {
            foreach ($value as $nested) {
                if ($this->containsInstructionLikePayload($nested)) {
                    return true;
                }
            }
        }
        return false;
    }
}
