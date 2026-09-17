<?php

namespace App\Services\Models;

use App\Contracts\NexusModel;
use App\Exceptions\NexusModelException;
use App\Nexus\NexusModelCapabilities;
use App\Nexus\NexusModelRequest;
use App\Nexus\NexusModelResponse;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Log;

final class LocalNexusModel implements NexusModel
{
    public function complete(NexusModelRequest $request): NexusModelResponse
    {
        $config = config('nexus.ai.local');
        $script = $this->path((string) $config['script']);
        $checkpoint = $this->path((string) $config['checkpoint']);
        $tokenizer = $this->path((string) $config['tokenizer']);

        foreach ([$script, $checkpoint, $tokenizer] as $path) {
            if (! is_file($path)) {
                throw new NexusModelException('Falta un artefacto del runtime local: '.$path, 'local_artifact_missing');
            }
        }

        $process = new Process([
            (string) ($config['python'] ?: 'python'),
            $script,
            '--checkpoint', $checkpoint,
            '--tokenizer', $tokenizer,
            '--max-context-tokens', (string) $config['max_context_tokens'],
            '--max-new-tokens', (string) $config['max_new_tokens'],
            '--temperature', (string) $config['temperature'],
            '--top-k', (string) $config['top_k'],
            '--top-p', (string) $config['top_p'],
            '--timeout', (string) $config['timeout'],
            '--logits-cache-size', (string) $config['logits_cache_size'],
            '--quantization', (string) $config['quantization'],
        ], base_path());
        $process->setTimeout((float) $config['timeout'] + 5);
        $process->setInput(json_encode([
            'prompt' => $request->message,
            'context' => $request->toArray(),
            'stream' => false,
        ], JSON_THROW_ON_ERROR).PHP_EOL);
        $process->run();

        if (! $process->isSuccessful()) {
            $events = array_values(array_filter(array_map(
                fn (string $line): ?array => json_decode($line, true),
                preg_split('/\R/', trim($process->getOutput())) ?: []
            )));
            $error = collect($events)->firstWhere('event', 'error');
            throw new NexusModelException(
                is_array($error) && isset($error['error'])
                    ? (string) $error['error']
                    : 'El runtime local terminó con error.',
                is_array($error) ? (string) ($error['code'] ?? 'local_runtime_failed') : 'local_runtime_failed'
            );
        }

        $events = array_values(array_filter(array_map(
            fn (string $line): ?array => json_decode($line, true),
            preg_split('/\R/', trim($process->getOutput())) ?: []
        )));
        $complete = collect($events)->firstWhere('event', 'complete');
        if (! is_array($complete) || ! is_array($complete['result'] ?? null)) {
            throw new NexusModelException('El runtime local no devolvió un resultado válido.', 'local_invalid_response');
        }

        $text = trim((string) ($complete['result']['text'] ?? ''));
        if ($text === '') {
            throw new NexusModelException('El modelo local devolvió una respuesta vacía.', 'invalid_inference_response');
        }
        Log::info('Nexus local inference completed.', [
            'offline' => true,
            'metrics' => $complete['result'],
        ]);
        return new NexusModelResponse(
            intent: 'local.inference',
            message: $text,
            toolCalls: [],
            requiresConfirmation: false,
            metadata: ['offline' => true, 'metrics' => $complete['result']],
        );
    }

    public function capabilities(): NexusModelCapabilities
    {
        return new NexusModelCapabilities(textGeneration: true, structuredOutput: false, toolCalling: false);
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
