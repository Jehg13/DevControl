<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class NexusValidationService
{
    public function validate(array $files = [], string $suite = 'php'): array
    {
        $checks = [];

        if (in_array($suite, ['php', 'all'], true)) {
            foreach ($files as $file) {
                $path = $this->safePath($file);
                if (! str_ends_with(strtolower($file), '.php')) {
                    continue;
                }

                $checks[] = $this->run([PHP_BINARY, '-l', $path], 'php -l '.$file);
            }
        }

        if (in_array($suite, ['routes', 'all'], true)) {
            $checks[] = $this->run([PHP_BINARY, 'artisan', 'route:list', '--no-ansi'], 'php artisan route:list');
        }

        if (in_array($suite, ['nexus', 'all'], true)) {
            $checks[] = $this->run([PHP_BINARY, 'artisan', 'test', '--filter=Nexus'], 'php artisan test --filter=Nexus');
        }

        return [
            'passed' => collect($checks)->every(fn (array $check) => $check['passed']),
            'checks' => $checks,
            'validated_files' => array_values($files),
            'suite' => $suite,
        ];
    }

    private function safePath(string $file): string
    {
        if ($file === '' || str_contains($file, '..') || str_starts_with($file, '/') || str_contains($file, ':')) {
            throw new \InvalidArgumentException('La ruta de validación no es segura.');
        }

        $path = realpath(base_path($file));
        if ($path === false || ! str_starts_with($path, realpath(base_path()))) {
            throw new \InvalidArgumentException('La ruta de validación está fuera del proyecto.');
        }

        return $path;
    }

    private function run(array $command, string $label): array
    {
        $process = new Process($command, base_path());
        $process->setTimeout(120);
        $process->run();

        return [
            'command' => $label,
            'passed' => $process->isSuccessful(),
            'output' => trim($process->getErrorOutput() ?: $process->getOutput()),
        ];
    }
}
