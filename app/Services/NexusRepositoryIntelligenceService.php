<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

final class NexusRepositoryIntelligenceService
{
    public function analyze(?string $relativePath = null): array
    {
        $root = $this->root($relativePath);
        $files = collect(File::allFiles($root))
            ->reject(fn (\SplFileInfo $file): bool => $this->isExcluded($file, $root))
            ->filter(fn (\SplFileInfo $file): bool => $file->getSize() <= 500000)
            ->take(400)
            ->values();

        $directories = collect(File::directories($root))
            ->map(fn (string $directory): string => $this->relative($directory, $root))
            ->filter(fn (string $value): bool => $value !== '')
            ->values()->all();

        $composer = $this->jsonFile($root.'/composer.json');
        $package = $this->jsonFile($root.'/package.json');
        $phpUnit = file_exists($root.'/phpunit.xml');
        $artisan = file_exists($root.'/artisan');

        $extensions = $files->map(fn (\SplFileInfo $file): string => strtolower($file->getExtension()))->filter()->countBy()->all();
        $languages = [];
        foreach (['php' => 'PHP', 'js' => 'JavaScript', 'ts' => 'TypeScript', 'vue' => 'Vue', 'json' => 'JSON', 'sql' => 'SQL', 'md' => 'Markdown'] as $ext => $language) {
            if (($extensions[$ext] ?? 0) > 0) {
                $languages[] = $language;
            }
        }

        $frameworks = [];
        if (($composer['require']['laravel/framework'] ?? null) !== null || $artisan) {
            $frameworks[] = 'Laravel';
        }
        if (($package['dependencies']['react'] ?? null) !== null || ($package['dependencies']['next'] ?? null) !== null) {
            $frameworks[] = 'Node.js';
        }
        if (($package['dependencies']['vue'] ?? null) !== null) {
            $frameworks[] = 'Vue';
        }

        $evidence = [];
        if (file_exists($root.'/composer.json')) {
            $evidence[] = ['source' => 'composer.json', 'detail' => 'Manifesto de dependencias PHP detectado.', 'path' => 'composer.json'];
        }
        if (file_exists($root.'/package.json')) {
            $evidence[] = ['source' => 'package.json', 'detail' => 'Manifesto de dependencias Node detectado.', 'path' => 'package.json'];
        }
        if ($phpUnit) {
            $evidence[] = ['source' => 'phpunit.xml', 'detail' => 'Configuración de pruebas detectada.', 'path' => 'phpunit.xml'];
        }
        if ($artisan) {
            $evidence[] = ['source' => 'artisan', 'detail' => 'Entrada Laravel detectada.', 'path' => 'artisan'];
        }

        return [
            'root' => $relativePath ?: '.',
            'directories' => $directories,
            'files' => $files->map(fn (\SplFileInfo $file): array => [
                'path' => str_replace('\\', '/', $file->getRelativePathname()),
                'extension' => strtolower($file->getExtension()),
                'size' => $file->getSize(),
            ])->values()->all(),
            'languages' => array_values(array_unique($languages)),
            'frameworks' => array_values(array_unique($frameworks)),
            'dependencies' => [
                'php' => $composer['require'] ?? [],
                'php_dev' => $composer['require-dev'] ?? [],
                'node' => $package['dependencies'] ?? [],
                'node_dev' => $package['devDependencies'] ?? [],
            ],
            'configuration' => [
                'composer' => file_exists($root.'/composer.json'),
                'package_json' => file_exists($root.'/package.json'),
                'phpunit' => $phpUnit,
                'artisan' => $artisan,
            ],
            'evidence' => $evidence,
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
        if ($full === false) {
            return base_path();
        }

        return $full;
    }

    private function relative(string $path, string $root): string
    {
        return ltrim(str_replace('\\', '/', str_replace($root, '', $path)), '/');
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

    private function jsonFile(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return [];
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : [];
    }
}
