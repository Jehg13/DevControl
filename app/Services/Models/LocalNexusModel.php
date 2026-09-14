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
        $script = base_path((string) $config['script']);
        $checkpoint = base_path((string) $config['checkpoint']);
        $tokenizer = base_path((string) $config['tokenizer']);

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
            'prompt' => json_encode($request->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'stream' => false,
        ], JSON_THROW_ON_ERROR).PHP_EOL);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new NexusModelException('El runtime local terminó con error.', 'local_runtime_failed');
        }

        $events = array_values(array_filter(array_map(
            fn (string $line): ?array => json_decode($line, true),
            preg_split('/\R/', trim($process->getOutput())) ?: []
        )));
        $complete = collect($events)->firstWhere('event', 'complete');
        if (! is_array($complete) || ! is_array($complete['result'] ?? null)) {
            throw new NexusModelException('El runtime local no devolvió un resultado válido.', 'local_invalid_response');
        }

        $text = (string) ($complete['result']['text'] ?? '');
        Log::info('Nexus local inference completed.', [
            'offline' => true,
            'metrics' => $complete['result'],
        ]);
        return new NexusModelResponse(
            intent: 'local.inference',
            message: $text !== '' ? $text : 'El runtime local no generó texto.',
            toolCalls: [],
            requiresConfirmation: false,
            metadata: ['offline' => true, 'metrics' => $complete['result']],
        );
    }

    public function capabilities(): NexusModelCapabilities
    {
        return new NexusModelCapabilities(textGeneration: true, structuredOutput: false, toolCalling: false);
    }
}
