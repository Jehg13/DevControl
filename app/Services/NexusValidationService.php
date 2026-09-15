<?php

namespace App\Services;

use Symfony\Component\Process\Process;

class NexusValidationService
{
    public function __construct(private readonly NexusTestResultParser $parser)
    {
    }

    public function validate(array $files = [], string $suite = 'php'): array
    {
        if (PHP_SAPI !== 'cli') {
            set_time_limit(0);
        }

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
            'failure_details' => collect($checks)
                ->flatMap(fn (array $check): array => $check['failure_details'] ?? [])
                ->values()
                ->all(),
            'validated_files' => array_values($files),
            'suite' => $suite,
            'summary' => $summary,
        ];
    }

    /** @param array<int, array{command:string,passed:bool,stdout:string,stderr:string,output:string,exit_code:int|null}> $checks */
    private function summarize(array $checks): array
    {
        $testChecks = array_values(array_filter(
            $checks,
            static fn (array $check): bool => str_contains(
                strtolower((string) ($check['command'] ?? '')),
                'artisan test'
            )
        ));

        if ($testChecks !== []) {
            $summary = [];
            foreach ([
                'tests', 'assertions', 'failures', 'errors',
                'skipped', 'incomplete', 'warnings', 'deprecations',
            ] as $key) {
                $values = array_map(
                    static fn (array $check): mixed => $check[$key] ?? null,
                    $testChecks
                );
                $knownValues = array_values(array_filter($values, static fn (mixed $value): bool => $value !== null));
                $summary[$key] = $knownValues === [] ? null : array_sum($knownValues);
            }

            return $summary + ['duration' => null];
        }

        return [
            'tests' => null,
            'assertions' => null,
            'failures' => null,
            'errors' => null,
            'skipped' => null,
            'incomplete' => null,
            'warnings' => null,
            'deprecations' => null,
            'duration' => null,
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
        $temporaryDirectory = storage_path('framework/testing');
        if (! is_dir($temporaryDirectory) && ! mkdir($temporaryDirectory, 0775, true) && ! is_dir($temporaryDirectory)) {
            throw new \RuntimeException('No se pudo preparar el directorio temporal de PHPUnit.');
        }

        $junitPath = null;
        if (str_contains($label, 'artisan test')) {
            $junitPath = $temporaryDirectory.DIRECTORY_SEPARATOR.'nexus-'.bin2hex(random_bytes(8)).'.xml';
            $command[] = '--log-junit='.$junitPath;
        }

        $environment = [];
        foreach ([
            'PATH',
            'PATHEXT',
            'SystemRoot',
            'WINDIR',
            'COMSPEC',
            'SYSTEMDRIVE',
            'USERPROFILE',
            'PHPRC',
            'PHP_INI_SCAN_DIR',
            'OPENSSL_CONF',
        ] as $name) {
            $value = getenv($name);
            if ($value !== false) {
                $environment[$name] = $value;
            }
        }
        $environment['TEMP'] = $temporaryDirectory;
        $environment['TMP'] = $temporaryDirectory;

        $process = new Process($command, base_path(), $environment);
        $process->setTimeout(600);
        $process->run();

        $stdout = $process->getOutput();
        $stderr = $process->getErrorOutput();
        $metrics = $this->parser->parse($stdout."\n".$stderr);
        if ($junitPath !== null && is_file($junitPath)) {
            $metrics['failure_details'] = $this->parseJunitFailures($junitPath)
                + $metrics['failure_details'];
            @unlink($junitPath);
        }

        return [
            'command' => $label,
            'passed' => $process->isSuccessful(),
            'success' => $process->isSuccessful(),
            'stdout' => trim($stdout),
            'stderr' => trim($stderr),
            'output' => trim($stdout."\n".$stderr),
            'exit_code' => $process->getExitCode(),
            ...$metrics,
        ];
    }

    /** @return array<int,array{test:string,class:string|null,method:string|null,message:string,file:string|null,line:int|null,trace:string}> */
    private function parseJunitFailures(string $path): array
    {
        $xml = simplexml_load_file($path);
        if ($xml === false) {
            return [];
        }

        $details = [];
        foreach ($xml->xpath('//testcase[failure or error]') ?: [] as $testCase) {
            $name = (string) ($testCase['name'] ?? '');
            $class = (string) ($testCase['class'] ?? '') ?: null;
            $failure = $testCase->failure ?? $testCase->error;
            $message = trim((string) ($failure['message'] ?? $failure));
            $trace = trim((string) $failure);
            $file = null;
            $line = null;
            if (preg_match('/(.*?\.php):(\d+)/', $trace, $location) === 1) {
                $file = $location[1];
                $line = (int) $location[2];
            }

            $details[] = [
                'test' => $class !== null ? $class.'::'.$name : $name,
                'class' => $class,
                'method' => $name !== '' ? $name : null,
                'message' => $message,
                'file' => $file,
                'line' => $line,
                'trace' => $trace,
            ];
        }

        return $details;
    }
}
