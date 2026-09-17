<?php

namespace App\Services;

use App\Contracts\NexusAiTransport;
use App\Exceptions\NexusAiTransportException;
use JsonException;
use Throwable;

class NexusAiPythonTransport implements NexusAiTransport
{
    public function send(array $payload): array
    {
        try {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new NexusAiTransportException(
                'serialization_error',
                'La solicitud de Nexus no pudo serializarse.',
                $exception
            );
        }

        $python = (string) config('nexus.ai.python.executable', 'python');
        $workingDirectory = (string) config('nexus.ai.python.working_directory', base_path());
        $command = escapeshellarg($python).' -m nexus_ai';
        $inheritedEnvironment = getenv();
        $environment = array_merge(
            is_array($inheritedEnvironment) ? $inheritedEnvironment : [],
            $_ENV,
            [
            'NEXUS_MEMORY_PATH' => (string) config('nexus.ai.python.memory_path', storage_path('app/nexus-ai/memory.json')),
            'NEXUS_MEMORY_TTL' => (string) config('nexus.ai.python.memory_ttl', 1800),
            'NEXUS_MEMORY_MAX_RECORDS' => (string) config('nexus.ai.python.memory_max_records', 50),
            ]
        );
        $pipes = [];
        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $workingDirectory,
            $environment
        );

        if (! is_resource($process)) {
            throw new NexusAiTransportException(
                'python_unavailable',
                'El proceso Python de Nexus no está disponible.'
            );
        }

        try {
            if (fwrite($pipes[0], $encoded.PHP_EOL) === false) {
                throw new NexusAiTransportException(
                    'transport_error',
                    'No fue posible enviar la solicitud al motor Python de Nexus.'
                );
            }
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $stdout = '';
            $stderr = '';
            $deadline = microtime(true) + max(1, (int) config('nexus.ai.python.timeout', 30));

            while (true) {
                $stdout .= stream_get_contents($pipes[1]);
                $stderr .= stream_get_contents($pipes[2]);
                $status = proc_get_status($process);

                if (! $status['running']) {
                    break;
                }

                if (microtime(true) >= $deadline) {
                    proc_terminate($process);
                    throw new NexusAiTransportException(
                        'timeout',
                        'El motor Python de Nexus excedió el tiempo de espera.'
                    );
                }

                usleep(10000);
            }

            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);

            if ($exitCode !== 0) {
                throw new NexusAiTransportException(
                    'python_error',
                    trim($stderr) !== ''
                        ? 'El motor Python de Nexus devolvió un error: '.trim($stderr)
                        : 'El motor Python de Nexus terminó con un error.'
                );
            }

            try {
                $response = json_decode(trim($stdout), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new NexusAiTransportException(
                    'invalid_response',
                    'El motor Python devolvió una respuesta JSON inválida.',
                    $exception
                );
            }

            if (! is_array($response)) {
                throw new NexusAiTransportException(
                    'invalid_response',
                    'El motor Python devolvió una respuesta incompleta.'
                );
            }

            if (isset($response['error'])) {
                $error = is_array($response['error']) ? $response['error'] : [];
                throw new NexusAiTransportException(
                    (string) ($error['code'] ?? 'python_error'),
                    (string) ($error['message'] ?? 'El motor Python rechazó la solicitud.')
                );
            }

            return $response;
        } catch (NexusAiTransportException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new NexusAiTransportException(
                'transport_error',
                'No fue posible comunicarse con el motor Python de Nexus.',
                $exception
            );
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            if (is_resource($process)) {
                proc_close($process);
            }
        }
    }
}
