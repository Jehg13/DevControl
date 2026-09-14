<?php

namespace App\Services;

use App\Exceptions\NexusToolPermissionException;
use App\Models\NexusPermissionAudit;
use App\Nexus\NexusToolContext;
use Illuminate\Support\Str;

class NexusPermissionManager
{
    private const RISKS = ['low', 'medium', 'high'];

    public function authorize(
        string $toolName,
        array $permissions,
        array $parameters,
        NexusToolContext $context,
        bool $requiresConfirmation,
    ): int {
        $permissions = array_values(array_unique(array_filter($permissions, 'is_string')));
        $risk = $this->risk($toolName, $permissions);
        $audit = NexusPermissionAudit::create([
            'nexus_run_id' => $context->runId,
            'usuario_id' => $context->user?->id,
            'proyecto_id' => $context->projectId ?? $parameters['project_id'] ?? $parameters['proyecto_id'] ?? null,
            'tool_name' => $toolName,
            'action' => $toolName,
            'risk_level' => $risk,
            'requested_permissions' => $permissions,
            'status' => 'requested',
            'metadata' => [
                'source' => $context->source,
                'mode' => $this->mode(),
                'parameters_hash' => hash('sha256', json_encode($parameters)),
            ],
        ]);

        if ($permissions === []) {
            return $this->deny($audit, 'permission_not_declared', 'La herramienta no declaró permisos explícitos.');
        }

        $granted = $this->grantedPermissions($permissions, $context);
        $missing = array_values(array_diff($permissions, $granted));
        if ($missing !== []) {
            return $this->deny($audit, 'permission_denied', 'Faltan permisos explícitos: '.implode(', ', $missing).'.');
        }

        if ($this->policyRequiresApproval($risk, $requiresConfirmation, $context)) {
            $audit->update([
                'granted_permissions' => $granted,
                'status' => 'approval_required',
            ]);
            return $audit->id;
        }

        $audit->update([
            'granted_permissions' => $granted,
            'status' => 'granted',
            'approved_at' => now(),
        ]);

        return $audit->id;
    }

    public function complete(int $auditId, bool $successful, array $result = []): void
    {
        NexusPermissionAudit::whereKey($auditId)->update([
            'status' => $successful ? 'succeeded' : 'failed',
            'result' => $result,
        ]);
    }

    public function requiresApproval(string $toolName, array $permissions, NexusToolContext $context, bool $requiresConfirmation): bool
    {
        return $this->policyRequiresApproval($this->risk($toolName, $permissions), $requiresConfirmation, $context);
    }

    public function risk(string $toolName, array $permissions): string
    {
        if (in_array('destructive_action', $permissions, true)
            || str_contains($toolName, 'delete')
            || str_contains($toolName, 'destroy')) {
            return 'high';
        }
        if (in_array('execute_command', $permissions, true)
            || in_array('github.write', $permissions, true)
            || in_array('nexus.write', $permissions, true)
            || in_array('devcontrol.write', $permissions, true)) {
            return 'medium';
        }

        return 'low';
    }

    public function policy(): array
    {
        return [
            'mode' => $this->mode(),
            'risks' => [
                'low' => 'Lectura y análisis.',
                'medium' => 'Cambios o acciones externas controladas.',
                'high' => 'Acciones destructivas o irreversibles.',
            ],
            'protected_permissions' => config('nexus.security.protected_permissions', []),
        ];
    }

    private function grantedPermissions(array $permissions, NexusToolContext $context): array
    {
        if ($context->grantedPermissions !== []) {
            return array_values(array_intersect($permissions, $context->grantedPermissions));
        }
        if ($context->user?->rol === 'admin') {
            return $permissions;
        }
        if ($context->system && config('nexus.security.allow_system', true)) {
            return $permissions;
        }

        return [];
    }

    private function policyRequiresApproval(string $risk, bool $requiresConfirmation, NexusToolContext $context): bool
    {
        if ($context->confirmed) {
            return false;
        }
        if ($requiresConfirmation || $risk === 'high') {
            return true;
        }

        return match ($this->mode()) {
            'safe' => $risk !== 'low',
            'assisted' => false,
            'autonomous' => ! in_array($context->toolName ?? '', config('nexus.security.autonomous_tools', []), true),
            default => true,
        };
    }

    private function mode(): string
    {
        $mode = strtolower((string) config('nexus.security.mode', 'safe'));
        return in_array($mode, ['safe', 'assisted', 'autonomous'], true) ? $mode : 'safe';
    }

    private function deny(NexusPermissionAudit $audit, string $code, string $message): int
    {
        $audit->update(['status' => $code, 'result' => ['error' => $message]]);
        throw new NexusToolPermissionException($message);
    }
}
