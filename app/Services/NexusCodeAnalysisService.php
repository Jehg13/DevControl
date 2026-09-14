<?php

namespace App\Services;

use App\Models\Proyecto;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class NexusCodeAnalysisService
{
    public function analyze(?int $projectId = null, ?string $relativePath = null, bool $includeDocumentation = true): array
    {
        $project = $projectId ? Proyecto::findOrFail($projectId) : null;
        $root = $this->resolveRoot($relativePath);
        $files = $this->files($root);

        return [
            'project' => $project ? [
                'id' => $project->id,
                'name' => $project->nombre,
                'technologies' => $this->projectTechnologies($project),
                'repository_url' => $project->repositorio_url,
            ] : null,
            'analyzed_path' => $relativePath ?: '.',
            'technologies' => $this->technologies($files),
            'structure' => $this->structure($files),
            'files' => collect($files)->map(fn (\SplFileInfo $file): array => [
                'path' => str_replace('\\', '/', $file->getRelativePathname()),
                'extension' => strtolower($file->getExtension()),
                'size' => $file->getSize(),
            ])->values()->all(),
            'relevant_files' => $this->relevantFiles($files),
            'relationships' => $this->relationships($files),
            'route_bindings' => $this->routeBindings($files),
            'symbols' => $this->symbols($files),
            'dependencies' => $this->dependencies($root),
            'documentation' => $includeDocumentation ? $this->documentation($root) : [],
            'fingerprint' => $this->fingerprintFiles($files),
            'read_only' => true,
        ];
    }

    public function fingerprint(?string $relativePath = null): string
    {
        return $this->fingerprintFiles($this->files($this->resolveRoot($relativePath)));
    }

    private function resolveRoot(?string $relativePath): string
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '' || str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            return base_path();
        }

        $root = realpath(base_path($relativePath));
        if ($root === false || ! str_starts_with($root, realpath(base_path()))) {
            throw new \InvalidArgumentException('La ruta de análisis debe estar dentro del proyecto.');
        }

        return $root;
    }

    private function files(string $root): array
    {
        if (File::isFile($root)) {
            $relativePath = str_replace(
                rtrim(str_replace('\\', '/', realpath(base_path())), '/').'/',
                '',
                str_replace('\\', '/', $root)
            );
            $file = new \Symfony\Component\Finder\SplFileInfo(
                $root,
                dirname($relativePath),
                $relativePath
            );

            return $file->getSize() <= 500000 ? [$file] : [];
        }

        $excluded = ['vendor', 'node_modules', 'storage', 'bootstrap/cache', '.git'];

        return collect(File::allFiles($root))
            ->reject(function (\SplFileInfo $file) use ($excluded, $root): bool {
                $relative = str_replace('\\', '/', ltrim(str_replace($root, '', $file->getPathname()), '/'));
                return collect($excluded)->contains(fn (string $directory): bool =>
                    $relative === $directory || str_starts_with($relative, $directory.'/')
                );
            })
            ->filter(fn (\SplFileInfo $file): bool => $file->getSize() <= 500000)
            ->values()
            ->all();
    }

    private function technologies(array $files): array
    {
        $extensions = collect($files)->map(fn (\SplFileInfo $file) => strtolower($file->getExtension()))->countBy();
        $technologies = [];

        if ($extensions->has('php')) {
            $technologies[] = 'PHP';
            if ($this->hasFile($files, 'artisan')) {
                $technologies[] = 'Laravel';
            }
        }
        foreach (['js' => 'JavaScript', 'ts' => 'TypeScript', 'vue' => 'Vue', 'jsx' => 'React', 'blade.php' => 'Blade', 'sql' => 'SQL', 'css' => 'CSS'] as $extension => $technology) {
            if ($this->hasExtension($files, $extension)) {
                $technologies[] = $technology;
            }
        }
        if ($this->hasFile($files, 'composer.json')) {
            $technologies[] = 'Composer';
        }
        if ($this->hasFile($files, 'package.json')) {
            $technologies[] = 'Node.js';
        }

        return ['detected' => array_values(array_unique($technologies)), 'extensions' => $extensions->all()];
    }

    private function structure(array $files): array
    {
        return collect($files)->groupBy(fn (\SplFileInfo $file) => dirname(
            str_replace('\\', '/', $file->getRelativePathname())
        ))
            ->map(fn ($group, $directory) => [
                'directory' => $directory === '.' ? '.' : $directory,
                'files' => $group->count(),
                'examples' => $group->take(8)->map(fn (\SplFileInfo $file) => $file->getFilename())->values()->all(),
            ])->values()->all();
    }

    private function relevantFiles(array $files): array
    {
        return collect($files)
            ->filter(fn (\SplFileInfo $file): bool => preg_match('/(composer\.json|package\.json|README|routes[\\\\\/]|config[\\\\\/]|app[\\\\\/](Http|Models|Services|Nexus)|database[\\\\\/]migrations)/i', $file->getPathname()) === 1)
            ->map(fn (\SplFileInfo $file) => [
                'path' => str_replace('\\', '/', $file->getRelativePathname()),
                'reason' => $this->fileReason($file->getRelativePathname()),
            ])->values()->all();
    }

    private function relationships(array $files): array
    {
        $relationships = [];
        foreach ($files as $file) {
            if (strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $content = File::get($file->getPathname());
            preg_match_all('/(?:use|new|extends|implements)\s+([A-Z][A-Za-z0-9_\\\\]+)/', $content, $matches);
            $targets = array_values(array_unique($matches[1] ?? []));
            if ($targets !== []) {
                $relationships[] = [
                    'file' => str_replace('\\', '/', $file->getRelativePathname()),
                    'references' => array_slice($targets, 0, 30),
                ];
            }
        }

        return $relationships;
    }

    private function symbols(array $files): array
    {
        $symbols = [];
        foreach ($files as $file) {
            if (strtolower($file->getExtension()) !== 'php') {
                continue;
            }
            $content = File::get($file->getPathname());
            preg_match_all('/\b(class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/', $content, $types);
            preg_match_all('/\bfunction\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $content, $functions);
            if (($types[2] ?? []) !== [] || ($functions[1] ?? []) !== []) {
                $symbols[] = [
                    'file' => str_replace('\\', '/', $file->getRelativePathname()),
                    'types' => array_values(array_unique($types[2] ?? [])),
                    'functions' => array_values(array_unique($functions[1] ?? [])),
                ];
            }
        }

        return $symbols;
    }

    private function routeBindings(array $files): array
    {
        $bindings = [];
        foreach ($files as $file) {
            if (preg_match('/(^|[\\\\\/])routes([\\\\\/].*)?\.php$/i', $file->getRelativePathname()) !== 1) {
                continue;
            }
            $content = File::get($file->getPathname());
            preg_match_all(
                '/Route::(?:get|post|put|patch|delete|match|any)\s*\(\s*[\'"]([^\'"]+)[\'"][^;]*?\[\s*([A-Za-z_][A-Za-z0-9_\\\\]*)::class\s*,\s*[\'"]([^\'"]+)[\'"]\s*\]/s',
                $content,
                $matches,
                PREG_SET_ORDER
            );
            foreach ($matches as $match) {
                $bindings[] = [
                    'route' => $match[1],
                    'file' => str_replace('\\', '/', $file->getRelativePathname()),
                    'controller' => $match[2],
                    'action' => $match[3],
                ];
            }
        }

        return $bindings;
    }

    private function dependencies(string $root): array
    {
        $dependencies = [];
        foreach (['composer.json', 'package.json'] as $manifest) {
            $path = $root.DIRECTORY_SEPARATOR.$manifest;
            if (! File::exists($path)) {
                continue;
            }
            $data = json_decode(File::get($path), true);
            $dependencies[$manifest] = [
                'require' => $data['require'] ?? [],
                'require_dev' => $data['require-dev'] ?? $data['devDependencies'] ?? [],
                'dependencies' => $data['dependencies'] ?? [],
            ];
        }

        return $dependencies;
    }

    private function documentation(string $root): array
    {
        return collect(File::files($root))
            ->filter(fn (\SplFileInfo $file): bool => preg_match('/^(README|CHANGELOG|CONTRIBUTING|ARCHITECTURE).*\.md$/i', $file->getFilename()) === 1)
            ->map(fn (\SplFileInfo $file) => [
                'file' => $file->getFilename(),
                'content' => Str::limit(File::get($file->getPathname()), 12000),
            ])->values()->all();
    }

    private function fingerprintFiles(array $files): string
    {
        $entries = collect($files)
            ->map(fn (\SplFileInfo $file): string => implode(':', [
                str_replace('\\', '/', $file->getRelativePathname()),
                $file->getSize(),
                $file->getMTime(),
            ]))
            ->sort()
            ->implode('|');

        return hash('sha256', $entries);
    }

    private function projectTechnologies(Proyecto $project): array
    {
        return is_array($project->tecnologias) ? $project->tecnologias : array_filter(array_map('trim', explode(',', (string) $project->tecnologias)));
    }

    private function hasFile(array $files, string $name): bool
    {
        return collect($files)->contains(fn (\SplFileInfo $file): bool => $file->getFilename() === $name);
    }

    private function hasExtension(array $files, string $extension): bool
    {
        return collect($files)->contains(fn (\SplFileInfo $file): bool => strtolower($file->getExtension()) === $extension);
    }

    private function fileReason(string $path): string
    {
        return match (true) {
            str_contains($path, 'routes') => 'Define puntos de entrada HTTP.',
            str_contains($path, 'Models') => 'Define entidades y relaciones de datos.',
            str_contains($path, 'Services') => 'Contiene lógica de aplicación reutilizable.',
            str_contains($path, 'Nexus') => 'Contiene herramientas y orquestación de Nexus.',
            str_contains($path, 'migrations') => 'Define la persistencia y evolución del esquema.',
            default => 'Archivo de configuración o documentación relevante.',
        };
    }
}
