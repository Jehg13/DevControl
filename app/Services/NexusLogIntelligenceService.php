<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

final class NexusLogIntelligenceService
{
    public function analyze(?string $relativePath = null): array
    {
        $root = $this->root($relativePath);
        $logFiles = collect(File::allFiles($root))
            ->filter(fn (\SplFileInfo $file): bool => preg_match('/\.(log|txt|out|err)$/i', $file->getFilename()) === 1)
            ->take(15)
            ->values();

        $entries = [];
        foreach ($logFiles as $file) {
            $path = str_replace('\\', '/', $file->getRelativePathname());
            $size = $file->getSize();
            if ($size > 250000) {
                continue;
            }
            $content = File::get($file->getPathname());
            $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];
            $hits = [];
            foreach ($lines as $index => $line) {
                if (preg_match('/(error|exception|failed|warn|fatal)/i', $line)) {
                    $hits[] = ['line' => $index + 1, 'message' => trim($line)];
                }
            }
            if ($hits !== []) {
                $entries[] = ['path' => $path, 'hits' => $hits];
            }
        }

        return ['root' => $relativePath ?: '.', 'log_files' => $entries, 'read_only' => true];
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
}
