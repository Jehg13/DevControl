<?php

namespace App\Services;

use Symfony\Component\Process\Process;

final class NexusAiHealthService
{
    public function check(): array
    {
        $enabled = (bool) config('nexus.ai.enabled', false);
        $python = (string) config('nexus.ai.local.python', 'python');
        $workingDirectory = (string) config('nexus.ai.python.working_directory', base_path());
        if (! is_dir($workingDirectory.DIRECTORY_SEPARATOR.'nexus_ai')) {
            $workingDirectory = getcwd();
        }
        $checkpoint = $this->path((string) config('nexus.ai.local.checkpoint'));
        $tokenizer = $this->path((string) config('nexus.ai.local.tokenizer'));

        $report = [
            'enabled' => $enabled,
            'python' => false,
            'model_path' => $checkpoint,
            'tokenizer_path' => $tokenizer,
            'model_exists' => is_file($checkpoint),
            'tokenizer_exists' => is_file($tokenizer),
            'model_loaded' => false,
            'tokenizer_loaded' => false,
            'inference_ready' => false,
        ];

        if (! $enabled) {
            $report['status'] = 'disabled';
            return $report;
        }

        $process = new Process([
            $python,
            '-m',
            'nexus_ai.health',
        ], $workingDirectory);
        $process->setTimeout((float) config('nexus.ai.local.timeout', 30) + 5);
        $process->run();
        $report['python'] = $process->isSuccessful() || $process->getExitCode() === 1;

        $decoded = json_decode(trim($process->getOutput()), true);
        if (is_array($decoded)) {
            $report = array_merge($report, $decoded);
        } elseif (! $report['python']) {
            $report['error'] = [
                'code' => 'python_unavailable',
                'message' => trim($process->getErrorOutput()) ?: 'Python no está disponible.',
            ];
        }

        $report['status'] = $report['inference_ready'] ? 'ready' : 'unavailable';
        return $report;
    }

    private function path(string $value): string
    {
        if (preg_match('/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/])/', $value) === 1) {
            return $value;
        }

        $basePath = base_path($value);
        $workingPath = getcwd().DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $value);
        if (is_file($basePath) || ! is_file($workingPath)) {
            return $basePath;
        }

        return $workingPath;
    }
}
