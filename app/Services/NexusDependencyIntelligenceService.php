<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

final class NexusDependencyIntelligenceService
{
    public function analyze(?string $relativePath = null): array
    {
        $root = $this->root($relativePath);
        $composer = $this->jsonFile($root.'/composer.json');
        $package = $this->jsonFile($root.'/package.json');

        $php = array_merge($composer['require'] ?? [], $composer['require-dev'] ?? []);
        $node = array_merge($package['dependencies'] ?? [], $package['devDependencies'] ?? []);

        $problematic = [];
        foreach ($php as $name => $version) {
            if (str_contains((string) $name, 'symfony') || str_contains((string) $name, 'laravel') || str_contains((string) $name, 'phpunit')) {
                $problematic[] = ['name' => $name, 'version' => $version, 'source' => 'composer', 'category' => 'framework'];
            }
        }
        foreach ($node as $name => $version) {
            if (str_contains((string) $name, 'vite') || str_contains((string) $name, 'webpack') || str_contains((string) $name, 'react')) {
                $problematic[] = ['name' => $name, 'version' => $version, 'source' => 'package.json', 'category' => 'frontend'];
            }
        }

        return [
            'root' => $relativePath ?: '.',
            'php_dependencies' => $php,
            'node_dependencies' => $node,
            'problematic_dependencies' => $problematic,
            'read_only' => true,
        ];
    }

    private function root(?string $relativePath): string
    {
        if (trim((string) ($relativePath ?: '.')) === '.' || trim((string) ($relativePath ?: '.')) === '') {
            return base_path();
        }

        $full = realpath(base_path(trim((string) $relativePath)));
        return $full !== false ? $full : base_path();
    }

    private function jsonFile(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        $decoded = json_decode((string) @file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }
}
