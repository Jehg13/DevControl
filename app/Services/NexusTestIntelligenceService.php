<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

final class NexusTestIntelligenceService
{
    public function analyze(?string $relativePath = null): array
    {
        $root = $this->root($relativePath);
        $tests = collect(File::allFiles($root))
            ->filter(fn (\SplFileInfo $file): bool => preg_match('/(Test\.php|test\.php|\.feature\.php|\.spec\.php)$/i', $file->getFilename()) === 1)
            ->take(200)
            ->values();

        $fails = [];
        foreach ($tests as $file) {
            $path = str_replace('\\', '/', $file->getRelativePathname());
            $content = File::get($file->getPathname());
            if (preg_match('/assert|it\(|test\(|scenario/i', $content) === 1) {
                $fails[] = ['path' => $path, 'status' => 'available', 'evidence' => 'Archivo de pruebas detectado.'];
            }
        }

        return [
            'root' => $relativePath ?: '.',
            'tests' => $fails,
            'phpunit_configured' => file_exists($root.'/phpunit.xml'),
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
}
