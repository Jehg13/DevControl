<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

final class NexusCodeIssueIntelligenceService
{
    public function analyze(?string $relativePath = null, int $maxFiles = 250): array
    {
        $root = $this->root($relativePath);
        $files = collect(File::allFiles($root))
            ->reject(fn (\SplFileInfo $file): bool => $this->isExcluded($file, $root))
            ->filter(fn (\SplFileInfo $file): bool => strtolower($file->getExtension()) === 'php')
            ->take($maxFiles)
            ->values();

        $issues = [];
        $suspicious = [];

        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getRelativePathname());
            $content = File::get($file->getPathname());
            $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];

            foreach ($lines as $index => $line) {
                $lineNumber = $index + 1;
                if (preg_match('/(?:exec\s*\(|shell_exec\s*\(|passthru\s*\(|system\s*\()/i', $line)) {
                    $issues[] = $this->issue($path, $lineNumber, 'process_execution', 'high', 'Ejecución de proceso desde código.', 'La línea usa un shell o proceso con posible inyección.', $line);
                    $suspicious[] = ['path' => $path, 'line' => $lineNumber, 'reason' => 'Ejecución de proceso'];
                }
                if (preg_match('/\b(?:unserialize|serialize)\s*\(/i', $line)) {
                    $issues[] = $this->issue($path, $lineNumber, 'serialization', 'high', 'Deserialización de entrada.', 'Se deserializa valor potencialmente no confiable.', $line);
                    $suspicious[] = ['path' => $path, 'line' => $lineNumber, 'reason' => 'Serialización'];
                }
                if (preg_match('/\$[A-Za-z_][A-Za-z0-9_]*\s*=\s*\$_(?:GET|POST|REQUEST|SERVER|COOKIE|ENV)/i', $line)) {
                    $issues[] = $this->issue($path, $lineNumber, 'input_to_variable', 'medium', 'Entrada directa en variables.', 'Valor externo se usa sin validación explícita.', $line);
                    $suspicious[] = ['path' => $path, 'line' => $lineNumber, 'reason' => 'Entrada directa'];
                }
                if (preg_match('/\b(?:SELECT\s*\*|select\s*\*)/i', $line)) {
                    $issues[] = $this->issue($path, $lineNumber, 'select_star', 'medium', 'Consulta SQL sin columnas explícitas.', 'Se extrae más contenido del necesario.', $line);
                    $suspicious[] = ['path' => $path, 'line' => $lineNumber, 'reason' => 'SQL amplio'];
                }
            }
        }

        return [
            'project' => $relativePath ?: '.',
            'issues' => $issues,
            'suspicious_files' => array_values(array_unique(array_map(fn (array $item): string => $item['path'], $suspicious))),
            'summary' => [
                'total_issues' => count($issues),
                'high' => count(array_filter($issues, fn (array $issue): bool => $issue['severity'] === 'high')),
                'medium' => count(array_filter($issues, fn (array $issue): bool => $issue['severity'] === 'medium')),
            ],
            'read_only' => true,
        ];
    }

    private function issue(string $path, int $line, string $code, string $severity, string $title, string $detail, string $snippet): array
    {
        return [
            'file' => $path,
            'line' => $line,
            'code' => $code,
            'severity' => $severity,
            'title' => $title,
            'detail' => $detail,
            'snippet' => trim($snippet),
            'evidence' => ['path' => $path, 'line' => $line],
        ];
    }

    private function root(?string $relativePath): string
    {
        $path = trim((string) ($relativePath ?: '.'));
        if ($path === '.' || $path === '') {
            return base_path();
        }

        $full = realpath(base_path($path));
        return $full !== false ? $full : base_path();
    }

    private function isExcluded(\SplFileInfo $file, string $root): bool
    {
        $relative = str_replace('\\', '/', ltrim(str_replace($root, '', $file->getPathname()), '/\\'));
        foreach (['vendor', 'node_modules', 'storage', 'bootstrap/cache', '.git'] as $directory) {
            if ($relative === $directory || str_starts_with($relative, $directory.'/')) {
                return true;
            }
        }

        return false;
    }
}
