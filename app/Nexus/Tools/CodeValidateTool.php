<?php

namespace App\Nexus\Tools;

use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusValidationService;

class CodeValidateTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusValidationService $validation)
    {
    }

    public function name(): string
    {
        return 'nexus.code.validate';
    }

    public function description(): string
    {
        return 'Valida cambios de código sin modificarlos mediante lint PHP, rutas Laravel y pruebas focalizadas de Nexus.';
    }

    public function parameters(): array
    {
        return [
            'files' => ['type' => 'array', 'required' => false],
            'suite' => ['type' => 'string', 'required' => false, 'enum' => ['php', 'routes', 'nexus', 'all']],
        ];
    }

    public function permissions(): array
    {
        return ['nexus.read'];
    }

    protected function validationRules(): array
    {
        return [
            'files' => ['nullable', 'array', 'max:50'],
            'files.*' => ['string', 'max:300'],
            'suite' => ['nullable', 'in:php,routes,nexus,all'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        return NexusToolResult::success($this->validation->validate(
            $parameters['files'] ?? [],
            $parameters['suite'] ?? 'php'
        ));
    }
}
