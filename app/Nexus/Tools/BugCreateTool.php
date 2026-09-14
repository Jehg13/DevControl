<?php

namespace App\Nexus\Tools;

use App\Models\Bug;
use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use Illuminate\Validation\Rule;

class BugCreateTool extends AbstractNexusTool
{
    public function name(): string
    {
        return 'devcontrol.bugs.create';
    }

    public function description(): string
    {
        return 'Registra un bug con folio, estado y prioridad.';
    }

    public function parameters(): array
    {
        return [
            'project_id' => ['type' => 'integer', 'required' => true],
            'title' => ['type' => 'string', 'required' => true],
            'description' => ['type' => 'string', 'required' => false],
            'status' => ['type' => 'string', 'required' => false],
            'priority' => ['type' => 'string', 'required' => false],
            'detected_at' => ['type' => 'date', 'required' => false],
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
            'status' => ['nullable', Rule::in(['Reportado', 'Investigando', 'En desarrollo', 'En pruebas', 'Solucionado', 'Cerrado'])],
            'priority' => ['nullable', Rule::in(['Baja', 'Media', 'Alta'])],
            'detected_at' => ['nullable', 'date'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        $lastNumber = Bug::query()
            ->where('folio', 'like', 'BUG-%')
            ->orderByDesc('id')
            ->value('folio');
        $nextNumber = $lastNumber ? ((int) str_replace('BUG-', '', $lastNumber)) + 1 : 1;

        $bug = Bug::create([
            'folio' => 'BUG-'.str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT),
            'proyecto_id' => $parameters['project_id'],
            'titulo' => $parameters['title'],
            'descripcion' => $parameters['description'] ?? null,
            'estado' => $parameters['status'] ?? 'Reportado',
            'prioridad' => $parameters['priority'] ?? 'Media',
            'fecha_detectado' => $parameters['detected_at'] ?? now()->toDateString(),
        ]);

        return NexusToolResult::success($bug->fresh('proyecto'));
    }
}
