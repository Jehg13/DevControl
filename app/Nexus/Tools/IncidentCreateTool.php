<?php

namespace App\Nexus\Tools;

use App\Models\Incidente;
use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use Illuminate\Validation\Rule;

class IncidentCreateTool extends AbstractNexusTool
{
    public function name(): string
    {
        return 'devcontrol.incidents.create';
    }

    public function description(): string
    {
        return 'Registra un incidente asociado a un proyecto.';
    }

    public function parameters(): array
    {
        return [
            'project_id' => ['type' => 'integer', 'required' => true],
            'title' => ['type' => 'string', 'required' => true],
            'description' => ['type' => 'string', 'required' => false],
            'priority' => ['type' => 'string', 'required' => false],
            'status' => ['type' => 'string', 'required' => false],
        ];
    }

    public function permissions(): array
    {
        return ['devcontrol.write'];
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    protected function validationRules(): array
    {
        return [
            'project_id' => ['required', 'integer', 'exists:proyectos,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', Rule::in(['Alta', 'Media', 'Baja'])],
            'status' => ['nullable', Rule::in(['Abierto', 'En investigación', 'En resolución', 'Resuelto'])],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        $incident = Incidente::create([
            'proyecto_id' => $parameters['project_id'],
            'titulo' => $parameters['title'],
            'descripcion' => $parameters['description'] ?? null,
            'prioridad' => $parameters['priority'] ?? 'Media',
            'estado' => $parameters['status'] ?? 'Abierto',
            'fecha_detectado' => now()->toDateString(),
        ]);

        return NexusToolResult::success($incident->fresh('proyecto'));
    }
}
