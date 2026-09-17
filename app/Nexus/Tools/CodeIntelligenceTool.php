<?php

namespace App\Nexus\Tools;

use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use Illuminate\Support\Str;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class CodeIntelligenceTool extends AbstractNexusTool
{
    private const EXTENSIONS = [
        'php' => 'PHP',
        'js' => 'JavaScript',
        'jsx' => 'JavaScript',
        'ts' => 'TypeScript',
        'tsx' => 'TypeScript',
        'py' => 'Python',
        'dart' => 'Dart',
        'sql' => 'SQL',
        'html' => 'HTML',
        'css' => 'CSS',
        'scss' => 'SCSS',
        'sh' => 'Bash',
        'ps1' => 'PowerShell',
    ];

    private const EXCLUDED = [
        '.git', 'vendor', 'node_modules', 'storage', 'bootstrap/cache',
    ];

    public function __construct(private readonly ?string $workspace = null)
    {
    }

    public function name(): string
    {
        return 'nexus.code.inspect';
    }

    public function description(): string
    {
        return 'Inspecciona la estructura y los símbolos de un proyecto en modo solo lectura.';
    }

    public function parameters(): array
    {
        return [
            'path' => [
                'type' => 'string',
                'required' => false,
                'description' => 'Ruta relativa al workspace que se analizará.',
            ],
            'max_files' => [
                'type' => 'integer',
                'required' => false,
                'description' => 'Límite de archivos analizados; máximo 500.',
            ],
        ];
    }

    public function permissions(): array
    {
        return ['nexus.read'];
    }

    protected function validationRules(): array
    {
        return [
            'path' => ['nullable', 'string', 'max:255'],
            'max_files' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        $workspace = realpath($this->workspace ?: base_path());
        $relativePath = trim((string) ($parameters['path'] ?? ''), " \t\n\r\0\x0B\\/");
        $target = $workspace === false
            ? false
            : realpath($workspace.DIRECTORY_SEPARATOR.$relativePath);

        if ($workspace === false || $target === false || ! $this->isWithin($target, $workspace)) {
            return NexusToolResult::failure(
                'invalid_project_path',
                'La ruta del proyecto debe estar dentro del workspace autorizado.'
            );
        }

        if (! is_dir($target)) {
            return NexusToolResult::failure(
                'project_path_not_directory',
                'La ruta solicitada existe, pero no es un directorio de proyecto.'
            );
        }

        $maxFiles = (int) ($parameters['max_files'] ?? 200);
        $files = [];
        $directories = [];
        $symbols = [];
        $relations = [];
        $imports = [];

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $this->isExcluded($file, $target)) {
                continue;
            }
            $extension = strtolower($file->getExtension());
            if (! isset(self::EXTENSIONS[$extension]) || count($files) >= $maxFiles) {
                continue;
            }
            $relative = str_replace('\\', '/', ltrim(str_replace($target, '', $file->getPathname()), '\\/'));
            $files[] = [
                'path' => $relative,
                'type' => 'file',
                'language' => self::EXTENSIONS[$extension],
                'size' => $file->getSize(),
                'component' => $this->componentType($relative),
            ];
            $content = @file_get_contents($file->getPathname());
            if ($content === false) {
                continue;
            }
            $parsed = $this->parse($content, self::EXTENSIONS[$extension], $relative);
            $symbols = array_merge($symbols, $parsed['symbols']);
            $imports = array_merge($imports, $parsed['imports']);
            $relations = array_merge($relations, $parsed['relations']);
        }

        foreach ($files as $file) {
            $parts = explode('/', $file['path']);
            array_pop($parts);
            foreach ($parts as $index => $part) {
                $directory = implode('/', array_slice($parts, 0, $index + 1));
                $directories[$directory] = [
                    'path' => $directory,
                    'type' => 'directory',
                ];
            }
        }

        return NexusToolResult::success([
            'project' => [
                'path' => $relativePath === '' ? '.' : $relativePath,
                'root_type' => 'directory',
                'read_only' => true,
            ],
            'files' => $files,
            'directories' => array_values($directories),
            'symbols' => $symbols,
            'imports' => $imports,
            'relations' => $relations,
        ], [
            'tool' => $this->name(),
            'files_analyzed' => count($files),
            'read_only' => true,
        ]);
    }

    private function parse(string $content, string $language, string $path): array
    {
        $symbols = [];
        $imports = [];
        $relations = [];
        $patterns = match ($language) {
            'PHP' => [
                'class' => '/\bclass\s+([A-Za-z_]\w*)/',
                'interface' => '/\binterface\s+([A-Za-z_]\w*)/',
                'method' => '/\bfunction\s+([A-Za-z_]\w*)\s*\(/',
                'import' => '/^\s*(?:use|require(?:_once)?)\s+[\'"]?([^;\'"]+)/m',
            ],
            'JavaScript', 'TypeScript' => [
                'class' => '/\bclass\s+([A-Za-z_]\w*)/',
                'interface' => '/\binterface\s+([A-Za-z_]\w*)/',
                'method' => '/\b(?:function\s+|const\s+|let\s+|var\s+)([A-Za-z_]\w*)\s*(?:=\s*)?(?:async\s*)?\(/',
                'import' => '/^\s*import\s+.*?\s+from\s+[\'"]([^\'"]+)[\'"]/m',
            ],
            default => [
                'class' => '/\bclass\s+([A-Za-z_]\w*)/',
                'interface' => '/\binterface\s+([A-Za-z_]\w*)/',
                'method' => '/\b(?:def|function)\s+([A-Za-z_]\w*)\s*\(/',
                'import' => '/^\s*(?:import|from|include)\s+([^\s;]+)/m',
            ],
        };

        foreach (['class', 'interface', 'method'] as $kind) {
            preg_match_all($patterns[$kind], $content, $matches);
            foreach (array_unique($matches[1] ?? []) as $name) {
                $symbols[] = [
                    'name' => $name,
                    'kind' => $kind === 'method' ? 'method' : $kind,
                    'file' => $path,
                ];
            }
        }
        preg_match_all($patterns['import'], $content, $matches);
        foreach (array_unique($matches[1] ?? []) as $import) {
            $imports[] = ['file' => $path, 'target' => trim($import)];
        }

        $routePattern = '/\b(?:Route|router)\s*(?:->|::)?\s*(?:get|post|put|patch|delete|resource)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*([^\)]+)/i';
        preg_match_all($routePattern, $content, $routeMatches);
        foreach ($routeMatches[1] ?? [] as $index => $route) {
            $relations[] = [
                'type' => 'route_to_controller',
                'source' => $path,
                'route' => $route,
                'target' => trim($routeMatches[2][$index] ?? ''),
            ];
        }

        return compact('symbols', 'imports', 'relations');
    }

    private function componentType(string $path): string
    {
        $normalized = strtolower($path);
        if (str_contains($normalized, 'routes/')) {
            return 'route';
        }
        if (str_contains($normalized, 'controller')) {
            return 'controller';
        }
        if (str_contains($normalized, 'model')) {
            return 'model';
        }
        if (str_contains($normalized, 'service')) {
            return 'service';
        }
        if (str_contains($normalized, 'config/')) {
            return 'configuration';
        }
        return 'source';
    }

    private function isExcluded(SplFileInfo $file, string $target): bool
    {
        $relative = str_replace('\\', '/', ltrim(str_replace($target, '', $file->getPathname()), '\\/'));
        foreach (self::EXCLUDED as $directory) {
            if ($relative === $directory || Str::startsWith($relative, $directory.'/')) {
                return true;
            }
        }
        return in_array(basename($file->getFilename()), ['.env', '.env.example'], true);
    }

    private function isWithin(string $path, string $root): bool
    {
        $root = rtrim(str_replace('\\', '/', $root), '/').'/';
        return Str::startsWith(str_replace('\\', '/', $path).'/', $root);
    }
}
