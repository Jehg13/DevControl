<?php

namespace Tests\Unit;

use App\Contracts\NexusAiTransport;
use App\Nexus\NexusRuntime;
use App\Nexus\NexusRuntimeRequest;
use App\Services\NexusAiPythonBridge;
use App\Services\NexusAiPythonTransport;
use App\Exceptions\NexusAiTransportException;
use Tests\TestCase;

class NexusAiPythonBridgeTest extends TestCase
{
    public function test_request_and_response_are_serialized_across_the_bridge(): void
    {
        $transport = new class implements NexusAiTransport {
            public array $payload = [];

            public function send(array $payload): array
            {
                $this->payload = $payload;

                return [
                    'interpretation' => [
                        'intent' => 'query_bug',
                        'confidence' => 'high',
                    ],
                    'context_used' => ['facts' => [['id' => 4]]],
                    'response' => 'Hay un bug confirmado.',
                    'status' => 'answered',
                    'evidence' => [[
                        'source' => 'laravel_tool',
                        'entity' => 'bug',
                        'record_ids' => [4],
                    ]],
                    'plan' => [],
                    'proposed_actions' => [],
                    'errors' => [],
                ];
            }
        };

        $bridge = new NexusAiPythonBridge($transport, app(\App\Nexus\NexusToolRegistry::class));
        $response = $bridge->handle(new NexusRuntimeRequest(
            message: 'Muéstrame los bugs.',
            user: \App\Models\User::factory()->make(['id' => 7]),
            projectId: 3,
            context: ['permissions' => ['nexus.read']],
            conversation: ['session_id' => 'test-session'],
            history: [['role' => 'user', 'content' => 'Revisa el proyecto.']],
        ));

        $this->assertSame('nexus_python', $response->source);
        $this->assertSame('query_bug', $response->intent);
        $this->assertSame('Hay un bug confirmado.', $response->finalMessage);
        $this->assertSame([4], $response->evidence[0]['record_ids']);
        $this->assertSame('3', (string) $transport->payload['context']['values']['project_id']);
        $this->assertSame('test-session', $transport->payload['metadata']['session_id']);
        $this->assertSame('user', $transport->payload['conversation'][0]['role']);
    }

    public function test_transport_errors_are_explicit_and_traceable(): void
    {
        $transport = new class implements NexusAiTransport {
            public function send(array $payload): array
            {
                throw new NexusAiTransportException('timeout', 'Tiempo agotado.');
            }
        };

        $bridge = new NexusAiPythonBridge($transport, app(\App\Nexus\NexusToolRegistry::class));
        $response = $bridge->handle(new NexusRuntimeRequest('Consulta'));

        $this->assertSame('failed', $response->status);
        $this->assertSame('nexus_python', $response->source);
        $this->assertStringContainsString('Tiempo agotado.', $response->errors[0]);
        $this->assertSame('timeout', $response->correlation['error_code']);
        $this->assertNotEmpty($response->correlation['request_id']);
    }

    public function test_invalid_python_responses_are_rejected(): void
    {
        $transport = new class implements NexusAiTransport {
            public function send(array $payload): array
            {
                return ['status' => 'answered'];
            }

        };

        $bridge = new NexusAiPythonBridge($transport, app(\App\Nexus\NexusToolRegistry::class));
        $response = $bridge->handle(new NexusRuntimeRequest('Consulta'));

        $this->assertSame('failed', $response->status);
        $this->assertSame('incomplete_response', $response->correlation['error_code']);
    }

    public function test_semantic_data_queries_are_allowlisted_before_tool_execution(): void
    {
        $transport = new class implements NexusAiTransport {
            public array $payloads = [];

            public function send(array $payload): array
            {
                $this->payloads[] = $payload;
                if (! ($payload['metadata']['data_resolved'] ?? false)) {
                    return [
                        'interpretation' => ['intent' => 'query_bug', 'confidence' => 'high'],
                        'response' => 'Datos solicitados.',
                        'status' => 'answered',
                        'data_requests' => [[
                            'entity' => 'bug',
                            'operation' => 'statistics',
                            'filters' => ['status' => 'Abierto', 'sql' => 'drop table'],
                            'sort' => ['field' => 'id', 'direction' => 'desc'],
                            'group_by' => ['status'],
                            'relations' => ['proyecto'],
                            'limit' => 10,
                            'permission_scope' => 'devcontrol.read',
                            'executable' => false,
                        ]],
                    ];
                }

                return [
                    'interpretation' => ['intent' => 'query_bug', 'confidence' => 'high'],
                    'response' => 'Hay datos confirmados.',
                    'status' => 'answered',
                    'data_requests' => [],
                    'evidence' => [],
                ];
            }
        };

        $bridge = new NexusAiPythonBridge($transport, app(\App\Nexus\NexusToolRegistry::class));
        $response = $bridge->handle(new NexusRuntimeRequest('Dame estadísticas de bugs.'));

        $this->assertSame('answered', $response->status);
        $this->assertTrue($transport->payloads[1]['metadata']['data_resolved']);
        $this->assertArrayHasKey('tool_results', $transport->payloads[1]['context']['values']);
    }

    public function test_runtime_can_use_real_local_python_transport_when_enabled(): void
    {
        config([
            'nexus.ai.python.enabled' => true,
            'nexus.ai.python.timeout' => 15,
        ]);

        $response = app(NexusRuntime::class)->handle(new NexusRuntimeRequest(
            message: '¿Qué hace DevControl?',
            context: ['permissions' => ['nexus.read']],
        ));

        $this->assertSame('nexus_python', $response->source);
        $this->assertNotSame('failed', $response->status);
        $this->assertNotEmpty($response->correlation['request_id']);
    }

    public function test_transport_can_be_constructed_for_process_failures_without_fallback_execution(): void
    {
        config([
            'nexus.ai.python.executable' => 'nexus-python-command-that-does-not-exist',
            'nexus.ai.python.timeout' => 1,
        ]);

        $transport = app(NexusAiPythonTransport::class);
        $this->expectException(NexusAiTransportException::class);
        $this->expectExceptionMessage('motor Python');
        $transport->send(['message' => 'Consulta']);
    }
}
