<?php

namespace App\Nexus\Tools;

use App\Models\NexusExperience;
use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusExperienceService;

class ExperienceSearchTool extends AbstractNexusTool
{
    public function __construct(private readonly NexusExperienceService $experiences)
    {
    }

    public function name(): string
    {
        return 'nexus.experience.search';
    }

    public function description(): string
    {
        return 'Recupera experiencias de trabajo similares, estrategias que funcionaron o fallaron y lecciones previamente registradas.';
    }

    public function parameters(): array
    {
        return [
            'query' => ['type' => 'string', 'required' => true],
            'project_id' => ['type' => 'integer', 'required' => false],
            'technologies' => ['type' => 'array', 'required' => false],
            'limit' => ['type' => 'integer', 'required' => false],
        ];
    }

    public function permissions(): array
    {
        return ['nexus.read'];
    }

    protected function validationRules(): array
    {
        return [
            'query' => ['required', 'string', 'max:4000'],
            'project_id' => ['nullable', 'integer', 'min:1'],
            'technologies' => ['nullable', 'array'],
            'technologies.*' => ['string', 'max:100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        return NexusToolResult::success([
            'experiences' => $this->experiences->similar(
                $parameters['query'],
                $parameters['project_id'] ?? $context->projectId,
                $parameters['technologies'] ?? [],
                (int) ($parameters['limit'] ?? 5),
            ),
        ]);
    }
}
