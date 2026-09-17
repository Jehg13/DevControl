<?php

namespace App\Nexus;

use App\Contracts\NexusTool;
use App\Exceptions\NexusToolPermissionException;
use App\Exceptions\NexusToolValidationException;
use Illuminate\Support\Facades\Validator;

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
        $this->authorize($context);

        $validator = Validator::make($parameters, $this->validationRules());
        if ($validator->fails()) {
            throw new NexusToolValidationException(
                'Los parámetros de la herramienta no son válidos.',
                $validator->errors()->toArray()
            );
        }

        if ($this->requiresConfirmation() && ! $context->confirmed) {
            return NexusToolResult::failure(
                'confirmation_required',
                'Esta herramienta requiere confirmación explícita antes de ejecutar cambios.',
                ['tool' => $this->name()]
            );
        }

        return $this->handle($validator->validated(), $context);
    }

    protected function validationRules(): array
    {
        return [];
    }

    abstract protected function handle(array $parameters, NexusToolContext $context): NexusToolResult;

    private function authorize(NexusToolContext $context): void
    {
        if ($this->permissions() === []) {
            return;
        }

        if ($context->isTrustedSystem()) {
            return;
        }

        if (! $context->user || $context->user->rol !== 'admin') {
            throw new NexusToolPermissionException();
        }
    }
}
