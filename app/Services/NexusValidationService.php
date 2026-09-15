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

        $summary = $this->summarize($checks);

        return [
            'passed' => collect($checks)->every(fn (array $check) => $check['passed']),
            'checks' => $checks,
            'validated_files' => array_values($files),
            'suite' => $suite,
            'summary' => $summary,
        ];
    }

    /** @param array<int, array{command:string,passed:bool,stdout:string,stderr:string,output:string,exit_code:int|null}> $checks */
    private function summarize(array $checks): array
    {
        $output = implode("\n", array_column($checks, 'output'));
        $extract = static function (string $pattern) use ($output): ?int {
            return preg_match($pattern, $output, $matches) === 1 ? (int) $matches[1] : null;
        };

        return [
            'tests' => $extract('/Tests:\s+(\d+)/i'),
            'assertions' => $extract('/Assertions:\s+(\d+)/i'),
            'failures' => $extract('/Failures:\s+(\d+)/i'),
            'errors' => $extract('/Errors:\s+(\d+)/i'),
            'skipped' => $extract('/Skipped:\s+(\d+)/i'),
            'incomplete' => $extract('/Incomplete:\s+(\d+)/i'),
            'warnings' => $extract('/Warnings:\s+(\d+)/i'),
            'deprecations' => $extract('/Deprecations:\s+(\d+)/i'),
            'duration' => preg_match('/Duration:\s+([0-9.]+s)/i', $output, $matches) === 1 ? $matches[1] : null,
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
            'stdout' => trim($process->getOutput()),
            'stderr' => trim($process->getErrorOutput()),
            'output' => trim($process->getOutput()."\n".$process->getErrorOutput()),
            'exit_code' => $process->getExitCode(),
        ];
    }
}
