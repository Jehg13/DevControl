<?php

namespace App\Nexus\Tools;

use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use Symfony\Component\Process\Process;

final class CodeValidateTool extends AbstractNexusTool
{
    public function name(): string
    {
        return 'nexus.code.validate';
    }

    public function description(): string
    {
        return 'Valida archivos modificados sin aplicar cambios.';
    }

    public function parameters(): array
    {
        return ['files' => ['type' => 'array', 'required' => true]];
    }

    public function permissions(): array
    {
        return ['nexus.code.validate'];
    }

    protected function validationRules(): array
    {
        return ['files' => ['required', 'array', 'min:1', 'max:50'], 'files.*' => ['required', 'string', 'max:255']];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        $checks = [];
        foreach ($parameters['files'] as $file) {
            $path = realpath(base_path($file));
            if ($path === false || ! is_file($path) || ! str_starts_with(str_replace('\\', '/', $path).'/', rtrim(str_replace('\\', '/', base_path()), '/').'/')) {
                return NexusToolResult::failure('invalid_validation_file', "El archivo [{$file}] no está dentro del proyecto.");
            }
            if (str_ends_with(strtolower($file), '.php')) {
                $process = new Process([PHP_BINARY, '-l', $path], base_path());
                $process->setTimeout(30);
                $process->run();
                $checks[] = [
                    'file' => $file,
                    'passed' => $process->isSuccessful(),
                    'output' => trim($process->getErrorOutput() ?: $process->getOutput()),
                ];
                continue;
            }
            $checks[] = ['file' => $file, 'passed' => trim((string) file_get_contents($path)) !== '', 'output' => 'readable'];
        }

        $passed = collect($checks)->every(fn (array $check): bool => $check['passed'] === true);
        return $passed
            ? NexusToolResult::success(['passed' => true, 'checks' => $checks], ['tool' => $this->name()])
            : NexusToolResult::failure('validation_failed', 'Las validaciones de código fallaron.', ['checks' => $checks]);
    }
}
