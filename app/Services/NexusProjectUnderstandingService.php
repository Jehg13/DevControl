<?php

namespace App\Services;

use App\Models\NexusProjectUnderstanding;
use App\Models\Proyecto;
use App\Services\NexusMemoryService;
use App\Exceptions\NexusGithubException;
use Illuminate\Support\Str;

class NexusProjectUnderstandingService
{
    public function __construct(
        private readonly NexusCodeAnalysisService $analysis,
        private readonly NexusMemoryService $memory,
        private readonly NexusKnowledgeEngine $knowledge,
    )
    {
    }

    public function understand(
        ?int $projectId = null,
        ?string $relativePath = null,
        bool $includeDocumentation = true,
        bool $refresh = false,
        ?int $userId = null,
    ): array {
        $sourcePath = trim((string) ($relativePath ?: '.')) ?: '.';
        $project = $projectId ? Proyecto::findOrFail($projectId) : null;
        $existing = NexusProjectUnderstanding::query()
            ->where('proyecto_id', $projectId)
            ->where('usuario_id', $userId)
            ->where('source_path', $sourcePath)
            ->latest('id')
            ->first();

        $currentFingerprint = $existing && ! $refresh
            ? $this->analysis->fingerprint($relativePath)
            : null;

        if ($existing && ! $refresh && $existing->source_fingerprint === $currentFingerprint) {
            return array_merge($existing->understanding ?? [], [
                'cached' => true,
                'stale' => false,
                'analyzed_at' => optional($existing->analyzed_at)->toIso8601String(),
            ]);
        }

        $evidence = $this->analysis->analyze($projectId, $relativePath, $includeDocumentation);
        if ($existing && $existing->source_fingerprint !== $evidence['fingerprint']) {
            $existing->update(['invalidated_at' => now()]);
        }

        $understanding = $this->buildUnderstanding($evidence, $project, $sourcePath);
        $record = NexusProjectUnderstanding::create([
            'proyecto_id' => $projectId,
            'usuario_id' => $userId,
            'source_path' => $sourcePath,
            'project_name' => $understanding['name'],
            'source_fingerprint' => $evidence['fingerprint'],
            'understanding' => $understanding,
            'analyzed_at' => now(),
        ]);
        $this->knowledge->indexUnderstanding(
            $understanding,
            $projectId,
            (string) $record->id,
            $evidence['fingerprint']
        );

        if ($projectId !== null && $userId !== null) {
            $conversation = $this->memory->conversation('project-understanding:'.$projectId, $userId);
            $this->memory->remember(
                $conversation,
                'knowledge',
                sprintf(
                    'El proyecto %s utiliza %s%s y tiene los módulos: %s.',
                    $understanding['name'],
                    $understanding['language'] ?: 'tecnología no determinada',
                    $understanding['framework'] ? ' con '.$understanding['framework'] : '',
                    implode(', ', array_keys($understanding['modules']))
                ),
                85,
                [
                    'project_id' => $projectId,
                    'source' => 'project_understanding',
                    'importance' => 75,
                    'fingerprint' => $evidence['fingerprint'],
                ]
            );
        }

        return array_merge($understanding, [
            'cached' => false,
            'stale' => false,
            'analyzed_at' => $record->analyzed_at?->toIso8601String(),
        ]);
    }

    public function query(
        string $question,
        ?int $projectId = null,
        ?string $relativePath = null,
        bool $refresh = false,
        ?int $userId = null,
    ): array {
        $understanding = $this->understand($projectId, $relativePath, true, $refresh, $userId);
        $section = $this->sectionFor($question);
        $result = $section ? ($understanding[$section] ?? []) : $understanding;

        return [
            'question' => $question,
            'section' => $section,
            'found' => $section === null || $result !== [],
            'answer' => $this->answer($question, $section, $result),
            'evidence' => $result,
            'project' => [
                'name' => $understanding['name'],
                'type' => $understanding['type'],
                'framework' => $understanding['framework'],
            ],
            'cached' => $understanding['cached'] ?? false,
        ];
    }

    public function invalidateForProject(int $projectId, string $reason): int
    {
        return NexusProjectUnderstanding::query()
            ->where('proyecto_id', $projectId)
            ->whereNull('invalidated_at')
            ->get()
            ->each(function (NexusProjectUnderstanding $understanding) use ($reason): void {
                $data = $understanding->understanding ?? [];
                $data['stale_reason'] = $reason;
                $understanding->update([
                    'invalidated_at' => now(),
                    'understanding' => $data,
                ]);
            })
            ->count();
    }

    private function buildUnderstanding(array $evidence, ?Proyecto $project, string $sourcePath): array
    {
        $paths = collect($evidence['files'] ?? [])->pluck('path')->all();
        if ($paths === []) {
            $paths = collect($evidence['relevant_files'] ?? [])->pluck('path')->all();
        }
        $allPaths = collect($evidence['structure'] ?? [])
            ->flatMap(fn (array $directory): array => collect($directory['examples'] ?? [])
                ->map(fn (string $file): string => trim(($directory['directory'] ?? '.').'/'.$file, './'))
                ->all())
            ->all();
        $knownPaths = array_values(array_unique(array_merge($paths, $allPaths)));
        $detected = $evidence['technologies']['detected'] ?? [];
        $type = $this->projectType($detected, $knownPaths);
        $framework = $this->framework($detected);
        $files = $this->classifyFiles($knownPaths);
        $routes = $this->routes($knownPaths);
        $modules = $this->modules($files);
        $entryPoints = $this->entryPoints($knownPaths, $type);
        $architecture = $this->architecture($type, $modules, $routes);
        $relations = $this->relations($evidence, $routes, $files);

        return [
            'name' => $project?->nombre ?: basename(base_path($sourcePath)) ?: config('app.name'),
            'type' => $type,
            'framework' => $framework,
            'language' => $this->language($detected),
            'structure' => $evidence['structure'] ?? [],
            'modules' => $modules,
            'entry_points' => $entryPoints,
            'routes' => $routes,
            'controllers' => $files['controllers'],
            'models' => $files['models'],
            'services' => $files['services'],
            'components' => $files['components'],
            'views' => $files['views'],
            'apis' => $this->apis($knownPaths, $routes),
            'migrations' => $files['migrations'],
            'configuration' => $files['configuration'],
            'dependencies' => $evidence['dependencies'] ?? [],
            'database' => $this->database($knownPaths, $evidence['dependencies'] ?? []),
            'architecture' => $architecture,
            'relationships' => $relations,
            'authentication' => $this->authentication($knownPaths, $relations),
            'detected_features' => $this->features($knownPaths, $evidence),
            'documentation' => $evidence['documentation'] ?? [],
            'evidence' => [
                'files' => $evidence['files'] ?? [],
                'relevant_files' => $evidence['relevant_files'] ?? [],
                'symbols' => $evidence['symbols'] ?? [],
                'technologies' => $evidence['technologies'] ?? [],
            ],
            'github' => $this->githubEvidence($project),
            'read_only' => true,
        ];
    }

    private function githubEvidence(?Proyecto $project): array
    {
        $integration = $project?->integracionGithub;
        if (! $integration && ! $project?->repositorio_url) {
            return ['configured' => false, 'repository' => null];
        }

        $url = $integration?->repositorio_url ?: $project?->repositorio_url;
        preg_match('#^https://github\.com/([^/]+)/([^/#]+?)(?:\.git)?$#i', rtrim((string) $url, '/'), $matches);

        return [
            'configured' => true,
            'repository' => isset($matches[1], $matches[2]) ? "{$matches[1]}/{$matches[2]}" : null,
            'branch' => $integration?->rama_principal,
            'status' => $integration?->estado,
            'last_commit' => $integration?->ultimo_commit_sha,
        ];
    }

    private function projectType(array $detected, array $paths): string
    {
        $lower = implode(' ', array_map('strtolower', $paths));
        return match (true) {
            in_array('Laravel', $detected, true) => 'laravel',
            in_array('Vue', $detected, true) => 'vue',
            in_array('React', $detected, true) => 'react',
            str_contains($lower, 'lib/main.dart') => 'flutter',
            in_array('Node.js', $detected, true) => 'node',
            in_array('PHP', $detected, true) => 'php',
            default => 'generic',
        };
    }

    private function framework(array $detected): ?string
    {
        foreach (['Laravel', 'Vue', 'React'] as $framework) {
            if (in_array($framework, $detected, true)) {
                return $framework;
            }
        }

        return null;
    }

    private function language(array $detected): ?string
    {
        foreach (['PHP', 'TypeScript', 'JavaScript', 'Dart'] as $language) {
            if (in_array($language, $detected, true)) {
                return $language;
            }
        }

        return null;
    }

    private function classifyFiles(array $paths): array
    {
        $groups = [
            'controllers' => ['app/Http/Controllers/', 'controller'],
            'models' => ['app/Models/', 'model'],
            'services' => ['app/Services/', 'service'],
            'components' => ['resources/js/components/', 'components/', '.vue'],
            'views' => ['resources/views/', 'views/', '.blade.php'],
            'migrations' => ['database/migrations/', 'migration'],
            'configuration' => ['config/', '.env.example', 'composer.json', 'package.json'],
        ];
        $result = array_fill_keys(array_keys($groups), []);
        foreach ($paths as $path) {
            foreach ($groups as $group => [$prefix, $marker]) {
                if (str_contains(strtolower($path), strtolower($prefix))
                    || str_contains(strtolower($path), strtolower($marker))) {
                    $result[$group][] = $path;
                }
            }
        }

        return array_map(fn (array $items): array => array_values(array_unique($items)), $result);
    }

    private function modules(array $files): array
    {
        return collect($files)->mapWithKeys(fn (array $items, string $name): array => [
            $name => ['files' => $items, 'count' => count($items)],
        ])->all();
    }

    private function entryPoints(array $paths, string $type): array
    {
        $patterns = $type === 'laravel'
            ? ['public/index.php', 'artisan', 'routes/web.php', 'routes/api.php']
            : ['index.php', 'index.js', 'index.ts', 'src/main.js', 'src/main.ts', 'lib/main.dart'];

        return array_values(array_filter($paths, fn (string $path): bool => in_array($path, $patterns, true)));
    }

    private function routes(array $paths): array
    {
        return array_values(array_filter($paths, fn (string $path): bool => preg_match('/(^|\/)routes?\/|router|route/i', $path) === 1));
    }

    private function apis(array $paths, array $routes): array
    {
        return array_values(array_filter(array_unique(array_merge(
            array_filter($routes, fn (string $path): bool => str_contains(strtolower($path), 'api')),
            array_filter($paths, fn (string $path): bool => preg_match('/(api|graphql|controller)/i', $path) === 1),
        ))));
    }

    private function database(array $paths, array $dependencies): array
    {
        return [
            'migrations' => array_values(array_filter($paths, fn (string $path): bool => str_contains(strtolower($path), 'migration'))),
            'drivers' => collect($dependencies)->flatMap(fn (array $manifest): array => array_keys($manifest['require'] ?? []))
                ->filter(fn (string $dependency): bool => preg_match('/(mysql|pgsql|sqlite|mongodb|doctrine)/i', $dependency) === 1)
                ->values()->all(),
            'evidence' => array_values(array_filter($paths, fn (string $path): bool => preg_match('/(database|model|migration|\.env)/i', $path) === 1)),
        ];
    }

    private function authentication(array $paths, array $relations): array
    {
        $matches = array_values(array_filter($paths, fn (string $path): bool => preg_match('/(auth|login|logout|middleware|guard|passport|sanctum|jwt|user)/i', $path) === 1));

        return ['detected' => $matches !== [], 'files' => $matches, 'relations' => array_values(array_filter($relations, fn (array $relation): bool => preg_match('/auth|user|login/i', json_encode($relation)) === 1))];
    }

    private function features(array $paths, array $evidence): array
    {
        $text = strtolower(implode(' ', $paths).' '.json_encode($evidence['dependencies'] ?? []));
        $features = [];
        foreach (['api', 'queue', 'event', 'notification', 'job', 'mail', 'github', 'test', 'migration'] as $feature) {
            if (str_contains($text, $feature)) {
                $features[] = $feature;
            }
        }

        return array_values(array_unique($features));
    }

    private function architecture(string $type, array $modules, array $routes): array
    {
        return [
            'style' => $type === 'laravel' ? 'MVC con servicios y rutas HTTP' : 'No determinado',
            'layers' => array_keys($modules),
            'request_flow' => $routes !== [] ? ['route', 'controller', 'service', 'model', 'database'] : [],
            'confidence' => $type === 'generic' ? 35 : 80,
        ];
    }

    private function relations(array $evidence, array $routes, array $files): array
    {
        $relations = collect($evidence['route_bindings'] ?? [])
            ->map(fn (array $binding): array => [
                'from' => $binding['route'],
                'to' => $binding['controller'],
                'type' => 'route_to_controller',
                'action' => $binding['action'],
                'file' => $binding['file'],
                'evidence' => 'route declaration',
            ])->all();
        foreach ($evidence['relationships'] ?? [] as $relation) {
            foreach ($relation['references'] ?? [] as $reference) {
                $relations[] = ['from' => $relation['file'], 'to' => $reference, 'type' => 'references', 'evidence' => 'source'];
            }
        }

        return $relations;
    }

    private function sectionFor(string $question): ?string
    {
        $question = Str::lower($question);
        return match (true) {
            str_contains($question, 'login') || str_contains($question, 'autentic') => 'authentication',
            str_contains($question, 'ruta') || str_contains($question, 'route') => 'routes',
            str_contains($question, 'controlador') => 'controllers',
            str_contains($question, 'modelo') => 'models',
            str_contains($question, 'servicio') => 'services',
            str_contains($question, 'tecnolog') || str_contains($question, 'framework') => 'evidence',
            str_contains($question, 'módulo') || str_contains($question, 'modulo') => 'modules',
            str_contains($question, 'base de datos') || str_contains($question, 'database') => 'database',
            default => null,
        };
    }

    private function answer(string $question, ?string $section, mixed $result): string
    {
        if ($section === null) {
            return 'No se pudo asociar la pregunta con una sección concreta; se devuelve la representación completa disponible.';
        }
        if ($result === [] || $result === null) {
            return "No se encontró evidencia suficiente para responder: {$question}";
        }

        return "La evidencia relacionada con {$section} está disponible en la representación estructurada del proyecto.";
    }
}
