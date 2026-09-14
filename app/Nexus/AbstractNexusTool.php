<?php

namespace App\Nexus;

use App\Contracts\NexusTool;
use App\Exceptions\NexusToolValidationException;
use Illuminate\Support\Facades\Validator;
use App\Services\NexusPermissionManager;

abstract class AbstractNexusTool implements NexusTool
{
    public function permissions(): array
    {
        return [];
    }

    public function requiresConfirmation(): bool
    {
        return false;
    }

    final public function execute(array $parameters, NexusToolContext $context): NexusToolResult
    {
        $auditId = app(NexusPermissionManager::class)->authorize(
            $this->name(),
            $this->permissions(),
            $parameters,
            new NexusToolContext(
                $context->user,
                $context->source,
                $context->confirmed,
                $context->system,
                $context->grantedPermissions,
                $context->runId,
                $context->projectId,
                $this->name()
            ),
            $this->requiresConfirmation()
        );

        $validator = Validator::make($parameters, $this->validationRules());
        if ($validator->fails()) {
            app(NexusPermissionManager::class)->complete($auditId, false, ['error' => 'validation_failed']);
            throw new NexusToolValidationException(
                'Los parámetros de la herramienta no son válidos.',
                $validator->errors()->toArray()
            );
        }

        if (app(NexusPermissionManager::class)->requiresApproval(
            $this->name(),
            $this->permissions(),
            new NexusToolContext(
                $context->user,
                $context->source,
                $context->confirmed,
                $context->system,
                $context->grantedPermissions,
                $context->runId,
                $context->projectId,
                $this->name()
            ),
            $this->requiresConfirmation()
        ) && ! $context->confirmed) {
            return NexusToolResult::failure(
                'confirmation_required',
                'Esta herramienta requiere confirmación explícita antes de ejecutar cambios.',
                ['tool' => $this->name()]
            );
        }

        $result = $this->handle($validator->validated(), $context);
        app(NexusPermissionManager::class)->complete($auditId, $result->successful, $result->toArray());

        return $result;
    }

    protected function validationRules(): array
    {
        return [];
    }

    abstract protected function handle(array $parameters, NexusToolContext $context): NexusToolResult;

}
