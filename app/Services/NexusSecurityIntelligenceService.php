<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

final class NexusSecurityIntelligenceService
{
    public function analyze(?string $relativePath = null): array
    {
        $root = $this->root($relativePath);
        $files = collect(File::allFiles($root))
            ->reject(fn (\SplFileInfo $file): bool => $this->isExcluded($file, $root))
            ->take(250)
            ->values();

        $findings = [];
        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getRelativePathname());
            $content = File::get($file->getPathname());
            foreach (preg_split('/\r\n|\n|\r/', $content) ?: [] as $index => $line) {
                $number = $index + 1;
                if (preg_match('/(?:password|secret|token|api[_-]?key|private[_-]?key|DB_PASSWORD|JWT_SECRET)/i', $line)) {
                    $findings[] = [
                        'file' => $path,
                        'line' => $number,
                        'evidence' => trim($line),
                        'risk' => 'possible_secret_exposure',
                        'severity' => 'high',
                    ];
                }
                if (preg_match('/(?:exec\s*\(|shell_exec\s*\(|passthru\s*\(|system\s*\()/i', $line)) {
                    $findings[] = [
                        'file' => $path,
                        'line' => $number,
                        'evidence' => trim($line),
                        'risk' => 'command_injection_risk',
                        'severity' => 'high',
                    ];
                }
            }
        }

        return [
            'root' => $relativePath ?: '.',
            'findings' => $findings,
            'summary' => ['count' => count($findings)],
            'read_only' => true,
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
