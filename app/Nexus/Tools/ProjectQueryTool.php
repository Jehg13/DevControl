<?php

namespace App\Nexus\Tools;

use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusProjectUnderstandingService;

class ProjectQueryTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusProjectUnderstandingService $understanding)
    {
    }

    public function name(): string
    {
        return 'nexus.project.query';
    }

    public function description(): string
    {
        return 'Consulta la comprensión estructurada persistente de un proyecto para responder preguntas sobre funcionamiento, autenticación, controladores, tecnologías, módulos y base de datos.';
    }

    public function parameters(): array
    {
        return [
            'question' => ['type' => 'string', 'required' => true],
            'project_id' => ['type' => 'integer', 'required' => false],
            'path' => ['type' => 'string', 'required' => false],
            'refresh' => ['type' => 'boolean', 'required' => false],
        ];
    }

    public function permissions(): array
    {
        return ['nexus.read'];
    }

    protected function validationRules(): array
    {
        return [
            'question' => ['required', 'string', 'max:2000'],
            'project_id' => ['nullable', 'integer', 'min:1'],
            'path' => ['nullable', 'string', 'max:300'],
            'refresh' => ['nullable', 'boolean'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        return NexusToolResult::success($this->understanding->query(
            $parameters['question'],
            $parameters['project_id'] ?? null,
            $parameters['path'] ?? null,
            (bool) ($parameters['refresh'] ?? false),
            $context->user?->id,
        ));
    }
}
