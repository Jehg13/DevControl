<?php

namespace App\Services;

use App\Models\NexusFinding;
use App\Models\Incidente;
use App\Models\Proyecto;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

class NexusAuditService
{
    public function scan(?int $projectId = null): array
    {
        if (! Schema::hasTable('nexus_hallazgos')) {
            throw new \RuntimeException('La tabla nexus_hallazgos no existe. Ejecuta php artisan migrate.');
        }

        $projects = Schema::hasTable('proyectos')
            ? Proyecto::query()->when($projectId, fn ($q) => $q->whereKey($projectId))->get()
            : collect();
        $findings = [];
        foreach ($projects as $project) {
            $findings = array_merge($findings, $this->projectFindings($project));
        }
        $findings = array_merge($findings, $this->moduleFindings());
        $saved = 0;
        foreach ($findings as $finding) {
            $storedFinding = NexusFinding::updateOrCreate(
                ['huella' => $finding['huella']],
                $finding + ['detectado_en' => now(), 'estado' => 'activo']
            );
            $this->createIncidentForFinding($storedFinding);
            $saved++;
        }
        return ['projects' => $projects->count(), 'detected' => count($findings), 'saved' => $saved];
    }

    private function createIncidentForFinding(NexusFinding $finding): void
    {
        if (! $finding->proyecto_id || ! Schema::hasTable('incidentes')) {
            return;
        }

        $marker = "[Nexus::finding:{$finding->id}]";
        if (Incidente::where('descripcion', 'like', '%'.$marker.'%')->exists()) {
            return;
        }

        Incidente::create([
            'proyecto_id' => $finding->proyecto_id,
            'titulo' => $finding->titulo,
            'descripcion' => "{$marker}\n\n{$finding->descripcion}\n\nIncidencia creada automáticamente por Nexus.",
            'prioridad' => match ($finding->severidad) {
                'critical', 'Crítica' => 'Alta',
                'high', 'Alta', 'warning' => 'Alta',
                default => 'Media',
            },
            'estado' => 'Abierto',
            'fecha_detectado' => now()->toDateString(),
        ]);
    }

    public function findings(?int $projectId = null, ?string $type = null)
    {
        return NexusFinding::query()->with('proyecto:id,nombre')
            ->when($projectId, fn ($q) => $q->where('proyecto_id', $projectId))
            ->when($type, fn ($q) => $q->where('tipo', $type))
            ->where('estado', 'activo')->latest('detectado_en')->get();
    }

    public function proposals(?int $projectId = null)
    {
        return $this->findings($projectId)->map(function (NexusFinding $finding): array {
            $proposal = match ($finding->tipo) {
                'tarea_vencida' => [
                    'accion' => 'Revisar la tarea y actualizar su estado o fecha límite.',
                    'archivos' => [],
                    'cambios' => 'No requiere modificar código; requiere una decisión sobre la tarea.',
                ],
                'funcionalidad' => [
                    'accion' => 'Revisar y resolver los bugs abiertos antes de cerrar el proyecto.',
                    'archivos' => [],
                    'cambios' => 'No se propone editar código automáticamente sin evidencia adicional.',
                ],
                'modulo' => [
                    'accion' => 'Registrar la ruta y conectar el módulo declarado, si corresponde.',
                    'archivos' => ['config/nexus.php', 'routes/web.php'],
                    'cambios' => 'Comparar la configuración del módulo con las rutas existentes y proponer una ruta antes de escribir.',
                ],
                'seguridad' => [
                    'accion' => 'Verificar que los secretos queden fuera del control de versiones.',
                    'archivos' => ['.gitignore'],
                    'cambios' => 'Agregar .env y archivos sensibles al .gitignore si todavía no están excluidos.',
                ],
                default => [
                    'accion' => 'Revisar el hallazgo con contexto adicional.',
                    'archivos' => [],
                    'cambios' => 'Nexus todavía no tiene una propuesta determinista para este tipo.',
                ],
            };

            $diff = $this->proposalDiff($finding, $proposal['archivos']);

            return [
                'hallazgo_id' => $finding->id,
                'proyecto' => $finding->proyecto?->nombre ?? 'Sistema',
                'severidad' => $finding->severidad,
                'titulo' => $finding->titulo,
                'accion' => $proposal['accion'],
                'archivos' => $proposal['archivos'],
                'cambios' => $proposal['cambios'],
                'diff' => $diff,
                'requiere_confirmacion' => true,
                'estado' => 'propuesta',
            ];
        });
    }

    public function proposal(int $index, ?int $projectId = null): ?array
    {
        return $this->proposals($projectId)->values()->get($index - 1);
    }

    public function applyProposal(int $findingId): array
    {
        $finding = NexusFinding::query()->whereKey($findingId)->where('estado', 'activo')->firstOrFail();
        $proposal = $this->proposals($finding->proyecto_id)->firstWhere('hallazgo_id', $finding->id);

        if (! $proposal || $proposal['diff'] === []) {
            throw new \RuntimeException('Este hallazgo no tiene un cambio de código determinista para aplicar.');
        }

        $applied = [];
        $backups = [];

        foreach ($proposal['diff'] as $change) {
            if (($change['estado'] ?? null) !== 'propuesta' || $change['archivo'] !== '.gitignore') {
                throw new \RuntimeException('El archivo o el tipo de cambio no está permitido para aplicación automática.');
            }

            $path = base_path($change['archivo']);
            if (! str_starts_with(realpath(dirname($path)), realpath(base_path()))
                || str_contains($change['archivo'], '..')) {
                throw new \RuntimeException('La ruta propuesta está fuera del proyecto.');
            }

            $backups[$path] = File::exists($path) ? File::get($path) : null;
            File::put($path, $change['nuevo']);
            $applied[] = $change['archivo'];
        }

        $validation = $this->validateAppliedFiles($applied);

        if (! $validation['passed']) {
            foreach ($backups as $path => $contents) {
                if ($contents === null) {
                    File::delete($path);
                } else {
                    File::put($path, $contents);
                }
            }

            throw new \RuntimeException(
                'Las validaciones fallaron y Nexus restauró los archivos. '.$validation['summary']
            );
        }

        $finding->update(['estado' => 'resuelto']);

        return [
            'finding_id' => $finding->id,
            'files' => $applied,
            'validation' => $validation,
        ];
    }

    private function validateAppliedFiles(array $files): array
    {
        $checks = [];

        foreach ($files as $file) {
            if (! str_ends_with(strtolower($file), '.php')) {
                continue;
            }

            $process = new Process([PHP_BINARY, '-l', base_path($file)], base_path());
            $process->run();
            $checks[] = [
                'command' => 'php -l '.$file,
                'passed' => $process->isSuccessful(),
                'output' => trim($process->getErrorOutput() ?: $process->getOutput()),
            ];
        }

        $routes = new Process([PHP_BINARY, 'artisan', 'route:list', '--no-ansi'], base_path());
        $routes->setTimeout(30);
        $routes->run();
        $checks[] = [
            'command' => 'php artisan route:list',
            'passed' => $routes->isSuccessful(),
            'output' => trim($routes->getErrorOutput() ?: $routes->getOutput()),
        ];

        $failed = collect($checks)->where('passed', false);

        return [
            'passed' => $failed->isEmpty(),
            'checks' => $checks,
            'summary' => $failed->isEmpty()
                ? 'Todas las validaciones posteriores pasaron.'
                : $failed->pluck('command')->implode(', '),
        ];
    }

    private function proposalDiff(NexusFinding $finding, array $files): array
    {
        if ($finding->tipo !== 'seguridad' || ! in_array('.gitignore', $files, true)) {
            return [];
        }

        $path = base_path('.gitignore');
        $current = File::exists($path) ? File::get($path) : '';
        $required = [".env\n", ".env.*\n", "*.key\n", "*.pem\n"];
        $missing = collect($required)->reject(fn (string $line) => str_contains($current, trim($line)))->values();

        if ($missing->isEmpty()) {
            return [[
                'archivo' => '.gitignore',
                'estado' => 'sin cambios',
                'detalle' => 'El archivo ya excluye los patrones sensibles recomendados.',
            ]];
        }

        return [[
            'archivo' => '.gitignore',
            'estado' => 'propuesta',
            'actual' => $current === '' ? '(archivo inexistente)' : $current,
            'nuevo' => rtrim($current)."\n\n# Nexus: archivos sensibles\n".implode('', $missing->all()),
        ]];
    }

    public function health(): array
    {
        $count = Schema::hasTable('nexus_hallazgos') ? NexusFinding::where('estado', 'activo')->count() : 0;
        return ['status' => $count === 0 ? 'saludable' : 'requiere atención', 'hallazgos_activos' => $count,
            'modules' => config('nexus.modules', []), 'rules' => config('nexus.rules', [])];
    }

    private function projectFindings(Proyecto $project): array
    {
        $result = [];
        if (Schema::hasTable('tareas')) {
            $project->loadMissing('tareas');
            foreach ($project->tareas as $task) {
                if ($task->fecha_limite && $task->fecha_limite->isPast()
                    && ! in_array($task->estado, ['Completado', 'Cancelado'], true)) {
                    $result[] = $this->finding($project->id, 'tarea_vencida', 'warning',
                        "Tarea vencida: {$task->titulo}", 'La tarea tiene fecha límite pasada y no está cerrada.', ['tarea_id' => $task->id]);
                }
            }
        }
        if (Schema::hasTable('secciones') && $project->secciones()->doesntExist()) {
            $result[] = $this->finding($project->id, 'modulo', 'info', 'Proyecto sin secciones',
                'El proyecto todavía no tiene secciones funcionales configuradas.');
        }
        if (Schema::hasTable('bugs') && $project->bugs()->whereNotIn('estado', ['Solucionado', 'Cerrado'])->count() > 0) {
            $result[] = $this->finding($project->id, 'funcionalidad', 'warning', 'Bugs abiertos',
                'El proyecto contiene bugs que aún requieren atención.');
        }
        return array_merge($result, $this->securityFindings($project));
    }

    private function securityFindings(Proyecto $project): array
    {
        $path = base_path();
        $files = File::exists($path.'/.env') ? ['.env'] : [];
        $result = [];
        if ($files) {
            $result[] = $this->finding($project->id, 'seguridad', 'info', 'Archivo .env local detectado',
                'Nexus no lee ni persiste valores secretos; verifica que este archivo no esté versionado.', ['archivos' => $files]);
        }
        return $result;
    }

    private function moduleFindings(): array
    {
        $result = [];
        foreach (config('nexus.modules', []) as $key => $module) {
            if (! \Illuminate\Support\Facades\Route::has($module['route'])) {
                $result[] = $this->finding(null, 'modulo', 'warning', "Ruta ausente: {$module['label']}",
                    'El módulo está declarado en la configuración central pero no tiene ruta registrada.', ['module' => $key]);
            }
        }
        return $result;
    }

    private function finding(?int $projectId, string $type, string $severity, string $title, string $description, array $metadata = []): array
    {
        $fingerprint = hash('sha256', implode('|', [$projectId, $type, $title, $description, json_encode($metadata)]));
        return [
            'proyecto_id' => $projectId,
            'tipo' => $type,
            'severidad' => $severity,
            'huella' => $fingerprint,
            'titulo' => $title,
            'descripcion' => $description,
            'metadata' => $metadata,
        ];
    }
}
