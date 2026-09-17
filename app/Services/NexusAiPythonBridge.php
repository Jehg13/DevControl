<?php

namespace App\Services;

use App\Contracts\NexusAiTransport;
use App\Exceptions\NexusAiTransportException;
use App\Nexus\NexusIdentity;
use App\Nexus\NexusRuntimeRequest;
use App\Nexus\NexusRuntimeResponse;
use App\Nexus\NexusToolRegistry;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class NexusAiPythonBridge
{
    public function __construct(
        private readonly NexusAiTransport $transport,
        private readonly NexusToolRegistry $tools,
    ) {
    }

    public function handle(NexusRuntimeRequest $request): NexusRuntimeResponse
    {
        $requestId = (string) Str::uuid();
        $payload = $this->toPythonRequest($request, $requestId);
        Log::info('Nexus Python request started.', ['request_id' => $requestId]);

        try {
            $response = $this->transport->send($payload);
            $response = $this->resolveDataRequests($response, $payload, $request);
            $result = $this->fromPythonResponse($response, $requestId);
            Log::info('Nexus Python request completed.', [
                'request_id' => $requestId,
                'status' => $result->status,
            ]);

            return $result;
        } catch (NexusAiTransportException $exception) {
            Log::error('Nexus Python request failed.', [
                'request_id' => $requestId,
                'error_code' => $exception->errorCode,
            ]);

            return $this->failure($requestId, $exception->errorCode, $exception->getMessage());
        } catch (Throwable $exception) {
            Log::error('Nexus Python response mapping failed.', [
                'request_id' => $requestId,
                'exception' => $exception,
            ]);

            return $this->failure(
                $requestId,
                'invalid_response',
                'La respuesta del motor Python no pudo interpretarse.'
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toPythonRequest(NexusRuntimeRequest $request, ?string $requestId = null): array
    {
        $context = $request->context;
        if ($request->projectId !== null) {
            $context['project_id'] = $request->projectId;
        }
        if ($request->project !== null) {
            $context['selected_project'] = is_object($request->project) && method_exists($request->project, 'toArray')
                ? $request->project->toArray()
                : $request->project;
        }

        return [
            'message' => $request->message,
            'user' => [
                'id' => $request->user?->id,
                'name' => $request->user?->name,
                'role' => $request->user?->role,
            ],
            'context' => [
                'values' => $context,
                'selected_project' => $context['selected_project'] ?? null,
                'relevant_memory' => $context['relevant_memory'] ?? [],
            ],
            'conversation' => $this->conversation($request->conversation, $request->history),
            'permissions' => $context['granted_permissions'] ?? $context['permissions'] ?? [],
            'tools' => $this->tools->definitions(),
            'identity' => [
                'name' => 'Nexus',
                'application' => 'DevControl',
                'role' => 'assistant',
                'instructions' => NexusIdentity::prompt(),
            ],
            'metadata' => array_merge($request->metadata, [
                'request_id' => $requestId,
                'session_id' => $request->conversation['session_id'] ?? 'runtime-'.($request->user?->id ?? 'guest'),
            ]),
        ];
    }

    private function fromPythonResponse(array $response, string $requestId): NexusRuntimeResponse
    {
        foreach (['interpretation', 'response', 'status'] as $required) {
            if (! array_key_exists($required, $response)) {
                throw new NexusAiTransportException(
                    'incomplete_response',
                    'El motor Python no devolvió el campo requerido: '.$required.'.'
                );
            }
        }

        if (! is_array($response['interpretation']) || ! is_string($response['response'])) {
                throw new NexusAiTransportException(
                    'invalid_response',
                    'El motor Python devolvió tipos de datos inválidos.'
                );
        }
        foreach (['context_used', 'plan', 'proposed_actions', 'errors'] as $arrayField) {
                if (isset($response[$arrayField]) && ! is_array($response[$arrayField])) {
                    throw new NexusAiTransportException(
                        'invalid_response',
                        'El campo '.$arrayField.' de la respuesta Python debe ser un arreglo.'
                    );
                }
        }

        $interpretation = $response['interpretation'];
        $contextUsed = $response['context_used'] ?? [];
        return new NexusRuntimeResponse(
                finalMessage: $response['response'],
                intent: (string) ($interpretation['intent'] ?? 'general'),
                evidence: $response['evidence'] ?? $contextUsed['facts'] ?? [],
                toolsUsed: [],
                certainty: strtoupper((string) ($interpretation['confidence'] ?? 'INSUFICIENTE')),
                missingInformation: $response['errors'] ?? [],
                errors: [],
                actions: $response['proposed_actions'] ?? [],
                plan: $response['plan'] ?? [],
                correlation: ['request_id' => $requestId],
                status: (string) $response['status'],
                source: 'nexus_python',
        );
    }

    private function resolveDataRequests(array $response, array $payload, NexusRuntimeRequest $request): array
    {
                $queries = $response['data_requests'] ?? [];
                if (! is_array($queries) || $queries === []) {
                    return $response;
                }

                $data = [];
                $toolResults = [];
                foreach ($queries as $query) {
                    if (! is_array($query) || ! isset($query['entity'], $query['operation'])) {
                        throw new NexusAiTransportException('invalid_data_request', 'Python devolvió una solicitud de datos inválida.');
                    }
                    $query = $this->validateDataQuery($query);
                    $projectName = $query['filters']['project_name'] ?? null;
                    foreach ($this->dataCalls($query, $request) as $call) {
                        if ($projectName !== null && $call['entity'] !== 'proyecto' && ($call['arguments']['project_id'] ?? null) === null) {
                            $projects = $data['proyecto'] ?? [];
                            $project = collect($projects)->first(
                                fn ($item): bool => is_array($item)
                                    && strcasecmp((string) ($item['nombre'] ?? ''), (string) $projectName) === 0
                            );
                            if ($project !== null) {
                                $call['arguments']['project_id'] = (int) $project['id'];
                            } else {
                                $data[$call['entity']] = [];
                                continue;
                            }
                        }
                        $tool = $this->tools->get($call['tool']);
                        if ($tool->requiresConfirmation() || in_array('devcontrol.write', $tool->permissions(), true)) {
                            throw new NexusAiTransportException('data_request_not_read_only', 'La consulta solicitada no es de solo lectura.');
                        }
                        $result = $this->tools->execute(
                            $call['tool'],
                            $call['arguments'],
                            new \App\Nexus\NexusToolContext(
                                user: $request->user,
                                source: 'python_data_query',
                                grantedPermissions: $payload['permissions'] ?? [],
                                projectId: $request->projectId,
                            )
                        );
                        $toolResult = [
                            'ok' => $result->successful,
                            'data' => $result->data,
                            'error' => $result->successful ? null : ['message' => $result->error],
                            'meta' => ['entity' => $call['entity'], 'tool' => $call['tool']],
                        ];
                        $toolResults[] = $toolResult;
                        if ($result->successful) {
                            $data[$call['entity']] = $result->data;
                        }
                    }
                }

                $followUp = $payload;
                $followUp['context']['values']['data'] = $data;
                $followUp['context']['values']['tool_results'] = $toolResults;
                $followUp['metadata']['data_resolved'] = true;

                return $this->transport->send($followUp);
    }

    private function dataCalls(array $query, NexusRuntimeRequest $request): array
    {
                $entity = (string) $query['entity'];
                $filters = is_array($query['filters'] ?? null) ? $query['filters'] : [];
                $projectId = $request->projectId;
                $tool = match ($entity) {
                    'proyecto' => 'devcontrol.projects.list',
                    'tarea' => 'devcontrol.tasks.list',
                    'bug' => 'devcontrol.bugs.list',
                    'incidente' => 'devcontrol.incidents.list',
                    'actualizacion' => 'devcontrol.updates.list',
                    'usuario' => 'devcontrol.users.list',
                    default => throw new NexusAiTransportException('unsupported_data_entity', 'Entidad de datos no soportada.'),
                };
                $arguments = array_filter([
                    'project_id' => $projectId,
                    'status' => $filters['status'] ?? null,
                    'priority' => $filters['priority'] ?? null,
                    'open_only' => ($filters['status'] ?? null) === 'Abierto',
                ], static fn ($value): bool => $value !== null && $value !== false);

                $calls = [['entity' => $entity, 'tool' => $tool, 'arguments' => $arguments]];
                if (isset($filters['project_name']) && $entity !== 'proyecto') {
                    $calls = [
                        ['entity' => 'proyecto', 'tool' => 'devcontrol.projects.list', 'arguments' => []],
                        ['entity' => $entity, 'tool' => $tool, 'arguments' => $arguments],
                    ];
                }
                if (($query['operation'] ?? '') === 'compare' && $entity === 'proyecto') {
                    $calls[] = ['entity' => 'bug', 'tool' => 'devcontrol.bugs.list', 'arguments' => []];
                    $calls[] = ['entity' => 'tarea', 'tool' => 'devcontrol.tasks.list', 'arguments' => []];
                }
                return $calls;
    }

    private function validateDataQuery(array $query): array
    {
        $entities = ['proyecto', 'tarea', 'bug', 'incidente', 'actualizacion', 'usuario'];
        $operations = ['list', 'search', 'count', 'detail', 'filter', 'sort', 'group', 'compare', 'statistics', 'relation'];
        if (! in_array($query['entity'], $entities, true)) {
            throw new NexusAiTransportException('invalid_data_entity', 'La entidad solicitada no está permitida.');
        }
        if (! in_array($query['operation'], $operations, true)) {
            throw new NexusAiTransportException('invalid_data_operation', 'La operación solicitada no está permitida.');
        }
        $filters = is_array($query['filters'] ?? null) ? $query['filters'] : [];
        $allowedFilters = ['project_id', 'project_name', 'status', 'priority', 'user_id'];
        $filters = array_filter(
            $filters,
            static fn ($value, $key): bool => in_array($key, $allowedFilters, true)
                && (is_string($value) || is_int($value) || is_bool($value)),
            ARRAY_FILTER_USE_BOTH
        );
        $sort = is_array($query['sort'] ?? null) ? $query['sort'] : [];
        $sortFields = ['id', 'nombre', 'name', 'titulo', 'title', 'status', 'priority', 'bugs_count', 'pending_tasks_count'];
        $sort = in_array($sort['field'] ?? null, $sortFields, true)
            && in_array($sort['direction'] ?? null, ['asc', 'desc'], true)
            ? ['field' => $sort['field'], 'direction' => $sort['direction']]
            : [];
        $groupFields = ['project_id', 'status', 'priority', 'user_id'];
        $groupBy = is_array($query['group_by'] ?? null) ? $query['group_by'] : [];
        $groupBy = array_values(array_intersect($groupBy, $groupFields));
        $relations = is_array($query['relations'] ?? null) ? $query['relations'] : [];
        $relations = array_values(array_intersect($relations, $entities));
        $limit = $query['limit'] ?? null;
        if (! is_int($limit) || $limit < 1 || $limit > 100) {
            $limit = null;
        }

        return [
            'entity' => $query['entity'],
            'operation' => $query['operation'],
            'filters' => $filters,
            'sort' => $sort,
            'group_by' => $groupBy,
            'relations' => $relations,
            'limit' => $limit,
            'context_requirements' => is_array($query['context_requirements'] ?? null)
                ? $query['context_requirements']
                : [],
            'permission_scope' => 'devcontrol.read',
            'executable' => false,
        ];
    }

    private function failure(string $requestId, string $code, string $message): NexusRuntimeResponse
    {
        return new NexusRuntimeResponse(
            finalMessage: 'Nexus no pudo completar la interpretación mediante Python.',
            errors: [$message],
            correlation: ['request_id' => $requestId, 'error_code' => $code],
            status: 'failed',
            source: 'nexus_python',
        );
    }

    private function conversation(array $conversation, array $history): array
    {
        $turns = array_merge($conversation, $history);

        return array_values(array_filter(array_map(
            static function ($turn): ?array {
                if (! is_array($turn) || ! isset($turn['role'], $turn['content'])) {
                    return null;
                }
                return [
                    'role' => (string) $turn['role'],
                    'content' => (string) $turn['content'],
                ];
            },
            $turns
        )));
    }
}
