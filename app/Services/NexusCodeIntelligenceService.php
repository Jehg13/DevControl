<?php

namespace App\Services;

use App\Contracts\NexusCodeParser;
use App\Models\NexusCodeFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class NexusCodeIntelligenceService
{
    /** @var array<int, NexusCodeParser> */
    private array $parsers;

    public function __construct(private readonly NexusKnowledgeGraphService $knowledge)
    {
        $this->parsers = [
            new class implements NexusCodeParser {
                public function language(): string { return 'PHP'; }
                public function supports(string $path, string $content): bool { return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'php'; }
                public function parse(string $path, string $content): array
                {
                    preg_match('/\bnamespace\s+([^;]+);/', $content, $namespace);
                    preg_match_all('/\b(class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/', $content, $types, PREG_SET_ORDER);
                    preg_match_all('/\bfunction\s+([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $content, $functions, PREG_SET_ORDER);
                    preg_match_all('/^\s*use\s+([^;]+);/m', $content, $imports);
                    preg_match_all('/\b(?:new|extends|implements)\s+([A-Za-z_][A-Za-z0-9_\\\\]*)/', $content, $references);
                    preg_match_all('/\b(?:DB::table|Schema::|Model::|->where|->query)\s*\(?\s*[\'"]?([A-Za-z0-9_.-]+)/', $content, $queries);
                    preg_match_all('/\b(?:belongsTo|hasMany|hasOne|morphMany)\s*\(\s*([A-Za-z_][A-Za-z0-9_\\\\]*)?/', $content, $relations);
                    return [
                        'namespace' => $namespace[1] ?? null,
                        'classes' => array_map(fn (array $match): array => ['kind' => $match[1], 'name' => $match[2]], $types),
                        'methods' => array_values(array_unique(array_column($functions, 1))),
                        'functions' => array_values(array_unique(array_column($functions, 1))),
                        'imports' => array_values(array_unique(array_map('trim', $imports[1] ?? []))),
                        'references' => array_values(array_unique($references[1] ?? [])),
                        'queries' => array_values(array_unique($queries[1] ?? [])),
                        'model_relations' => array_values(array_unique(array_filter($relations[1] ?? []))),
                    ];
                }
            },
            new class implements NexusCodeParser {
                public function language(): string { return 'JavaScript'; }
                public function supports(string $path, string $content): bool { return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['js', 'jsx', 'ts', 'tsx'], true); }
                public function parse(string $path, string $content): array
                {
                    preg_match_all('/\b(?:class|interface|type)\s+([A-Za-z_$][\w$]*)/', $content, $classes);
                    preg_match_all('/\b(?:function|const|let|var)\s+([A-Za-z_$][\w$]*)\s*(?:=|\\()/', $content, $functions);
                    preg_match_all('/^\s*import\s+.+?\s+from\s+[\'"]([^\'"]+)[\'"]|^\s*import\s+[\'"]([^\'"]+)[\'"]/m', $content, $imports);
                    preg_match_all('/\b(?:fetch|axios\.(?:get|post|put|delete))\s*\(\s*[\'"]([^\'"]+)/', $content, $apis);
                    return [
                        'classes' => array_values(array_unique($classes[1] ?? [])),
                        'functions' => array_values(array_unique($functions[1] ?? [])),
                        'imports' => array_values(array_unique(array_filter(array_merge($imports[1] ?? [], $imports[2] ?? [])))),
                        'api_calls' => array_values(array_unique($apis[1] ?? [])),
                    ];
                }
            },
            new class implements NexusCodeParser {
                public function language(): string { return 'Dart'; }
                public function supports(string $path, string $content): bool { return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'dart'; }
                public function parse(string $path, string $content): array
                {
                    preg_match_all('/^\s*import\s+[\'"]([^\'"]+)[\'"]/m', $content, $imports);
                    preg_match_all('/\bclass\s+([A-Za-z_]\w*)/', $content, $classes);
                    preg_match_all('/\b(?:void|Future|Widget|String|int|bool|double)\s+([A-Za-z_]\w*)\s*\(/', $content, $functions);
                    return ['classes' => array_values(array_unique($classes[1] ?? [])), 'functions' => array_values(array_unique($functions[1] ?? [])), 'imports' => array_values(array_unique($imports[1] ?? []))];
                }
            },
            new class implements NexusCodeParser {
                public function language(): string { return 'SQL'; }
                public function supports(string $path, string $content): bool { return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'sql'; }
                public function parse(string $path, string $content): array
                {
                    preg_match_all('/\b(?:from|join|into|update|table)\s+[`"]?([A-Za-z_][\w]*)/i', $content, $tables);
                    preg_match_all('/\b(?:select|insert|update|delete|create|alter|drop)\b/i', $content, $queries);
                    return ['tables' => array_values(array_unique($tables[1] ?? [])), 'queries' => array_values(array_unique(array_map('strtolower', $queries[0] ?? [])))];
                }
            },
        ];
    }

    public function analyze(?int $projectId = null, ?string $relativePath = null, bool $refresh = false): array
    {
        $root = $this->resolveRoot($relativePath);
        $files = $this->files($root);
        $changed = [];
        $unchanged = [];
        $nodes = [];
        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getRelativePathname());
            $content = File::get($file->getPathname());
            $fingerprint = hash('sha256', $path.':'.$file->getSize().':'.$file->getMTime());
            $record = NexusCodeFile::query()->where('proyecto_id', $projectId)->where('path', $path)->first();
            if (! $refresh && $record?->fingerprint === $fingerprint && $record->status === 'active') {
                $analysis = $record->analysis;
                $unchanged[] = $path;
            } else {
                if ($record && $record->fingerprint !== $fingerprint) {
                    $this->knowledge->invalidateSource(
                        'code_intelligence',
                        $record->fingerprint,
                        'El archivo cambió y sus relaciones serán reconstruidas.'
                    );
                }
                $parser = $this->parser($path, $content);
                $analysis = [
                    'path' => $path,
                    'language' => $parser?->language() ?? $this->language($path),
                    'symbols' => $parser?->parse($path, $content) ?? [],
                    'fingerprint' => $fingerprint,
                ];
                $record = NexusCodeFile::updateOrCreate(
                    ['proyecto_id' => $projectId, 'path' => $path],
                    ['fingerprint' => $fingerprint, 'language' => $analysis['language'], 'analysis' => $analysis, 'status' => 'active', 'invalidated_at' => null]
                );
                $changed[] = $path;
            }
            $nodes[] = $analysis;
        }

        $deleted = NexusCodeFile::query()
            ->where('proyecto_id', $projectId)
            ->whereNotIn('path', array_keys(array_flip(array_map(fn (array $node): string => $node['path'], $nodes))))
            ->where('status', 'active')
            ->get();
        foreach ($deleted as $record) {
            $record->update(['status' => 'invalidated', 'invalidated_at' => now()]);
        }

        $this->index($nodes, $projectId);
        return [
            'project_id' => $projectId,
            'analyzed_path' => $relativePath ?: '.',
            'files' => $nodes,
            'changed_files' => $changed,
            'unchanged_files' => $unchanged,
            'invalidated_files' => $deleted->pluck('path')->values()->all(),
            'impact' => $this->impact($changed, $nodes),
            'languages' => array_values(array_unique(array_column($nodes, 'language'))),
            'incremental' => true,
            'read_only' => true,
        ];
    }

    private function index(array $nodes, ?int $projectId): void
    {
        foreach ($nodes as $node) {
            $file = ['type' => 'file', 'key' => 'file:'.$node['path'], 'label' => $node['path']];
            foreach ((array) ($node['symbols']['classes'] ?? []) as $class) {
                $name = is_array($class) ? ($class['name'] ?? null) : $class;
                if (is_string($name)) {
                    $this->knowledge->relate($file, 'defines', ['type' => 'class', 'key' => 'class:'.$name, 'label' => $name], 'FACT', 'code_intelligence', $node['path'], $projectId, 95, $node['fingerprint']);
                }
            }
            foreach (array_merge((array) ($node['symbols']['methods'] ?? []), (array) ($node['symbols']['functions'] ?? [])) as $name) {
                if (is_string($name)) {
                    $this->knowledge->relate($file, 'defines', ['type' => 'function', 'key' => 'function:'.$name, 'label' => $name], 'FACT', 'code_intelligence', $node['path'], $projectId, 90, $node['fingerprint']);
                }
            }
            foreach (array_merge((array) ($node['symbols']['imports'] ?? []), (array) ($node['symbols']['references'] ?? [])) as $dependency) {
                if (is_string($dependency)) {
                    $this->knowledge->relate($file, 'depends_on', ['type' => 'dependency', 'key' => 'dependency:'.$dependency, 'label' => $dependency], 'FACT', 'code_intelligence', $node['path'], $projectId, 85, $node['fingerprint']);
                }
            }
            foreach (array_merge((array) ($node['symbols']['queries'] ?? []), (array) ($node['symbols']['tables'] ?? [])) as $table) {
                if (is_string($table)) {
                    $this->knowledge->relate($file, 'references_table', ['type' => 'table', 'key' => 'table:'.$table, 'label' => $table], 'FACT', 'code_intelligence', $node['path'], $projectId, 85, $node['fingerprint']);
                }
            }
        }
    }

    private function impact(array $changed, array $nodes): array
    {
        $changedSet = array_fill_keys($changed, true);
        $impact = [];
        foreach ($nodes as $node) {
            $dependencies = array_merge((array) ($node['symbols']['imports'] ?? []), (array) ($node['symbols']['references'] ?? []));
            if (isset($changedSet[$node['path']]) || array_intersect($dependencies, $changed)) {
                $impact[] = ['file' => $node['path'], 'reason' => isset($changedSet[$node['path']]) ? 'changed' : 'depends_on_changed_file'];
            }
        }
        return $impact;
    }

    private function parser(string $path, string $content): ?NexusCodeParser
    {
        foreach ($this->parsers as $parser) {
            if ($parser->supports($path, $content)) return $parser;
        }
        return null;
    }

    private function language(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'php' => 'PHP', 'dart' => 'Dart', 'sql' => 'SQL', 'js', 'jsx' => 'JavaScript', 'ts', 'tsx' => 'TypeScript', default => 'Unknown',
        };
    }

    private function resolveRoot(?string $relativePath): string
    {
        $relativePath = trim((string) $relativePath);
        $root = realpath($relativePath === '' ? base_path() : base_path($relativePath));
        if ($root === false || str_contains($relativePath, '..') || ! str_starts_with($root, realpath(base_path()))) throw new \InvalidArgumentException('La ruta de análisis debe estar dentro del proyecto.');
        return $root;
    }

    private function files(string $root): array
    {
        return collect(File::allFiles($root))->reject(fn (\SplFileInfo $file): bool => preg_match('/(^|[\\\\\/])(vendor|node_modules|storage|bootstrap\/cache|\.git)([\\\\\/]|$)/', str_replace('\\', '/', $file->getPathname())) === 1)->filter(fn (\SplFileInfo $file): bool => $file->getSize() <= 500000)->values()->all();
    }
}
