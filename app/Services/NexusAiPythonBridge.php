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
            $response = $this->executeProposedActions($response, $payload, $request, $requestId);
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

        $proposals = $this->normalizeToolProposals($response['proposed_actions'] ?? []);
        $source = 'nexus_python';

        $interpretation = $response['interpretation'];
        $contextUsed = $response['context_used'] ?? [];

        return new NexusRuntimeResponse(
            finalMessage: $response['response'],
            intent: (string) ($interpretation['intent'] ?? 'general'),
            evidence: $response['evidence'] ?? $contextUsed['facts'] ?? [],
            toolsUsed: array_values(array_filter(array_map(
                static fn (array $proposal): ?string => $proposal['tool'] ?? null,
                $proposals
            ))),
            certainty: strtoupper((string) ($interpretation['confidence'] ?? 'INSUFICIENTE')),
            missingInformation: $response['errors'] ?? [],
            errors: [],
            actions: $proposals,
            plan: $response['plan'] ?? [],
            correlation: [
                'request_id' => $requestId,
                'proposals' => array_map(static fn (array $proposal): array => [
                    'tool' => $proposal['tool'],
                    'operation' => $proposal['operation'] ?? 'read',
                    'correlation_id' => $proposal['correlation_id'] ?? $requestId,
                ], $proposals),
            ],
            status: (string) $response['status'],
            source: $source,
        );
    }

    private function normalizeToolProposals(array $actions): array
    {
        if ($actions === []) {
            return [];
        }

        return array_map(function ($proposal): array {
            if (! is_array($proposal)) {
                throw new NexusAiTransportException('invalid_tool_proposal', 'La propuesta de herramienta tiene un formato inválido.');
            }

            $tool = (string) ($proposal['tool'] ?? $proposal['tool_name'] ?? '');
            if ($tool === '') {
                throw new NexusAiTransportException('invalid_tool_proposal', 'La propuesta de herramienta no incluye nombre de herramienta.');
            }

            $arguments = is_array($proposal['arguments'] ?? $proposal['parameters'] ?? null)
                ? ($proposal['arguments'] ?? $proposal['parameters'])
                : [];
            $permissions = is_array($proposal['permissions'] ?? null)
                ? array_values(array_filter(array_map('strval', $proposal['permissions']), static fn (string $permission): bool => $permission !== ''))
                : [];
            $operation = strtolower((string) ($proposal['operation'] ?? 'read'));
            if (! in_array($operation, ['read', 'write', 'delete'], true)) {
                $operation = 'read';
            }

            $this->validateToolProposal($tool, $arguments, $permissions, $operation, $proposal);

            return [
                'tool' => $tool,
                'arguments' => $arguments,
                'permissions' => $permissions,
                'requires_confirmation' => (bool) ($proposal['requires_confirmation'] ?? ($operation !== 'read')),
                'operation' => $operation,
                'correlation_id' => (string) ($proposal['correlation_id'] ?? $proposal['correlationId'] ?? ''),
                'source' => (string) ($proposal['source'] ?? 'nexus_ai_python'),
                'executable' => (bool) ($proposal['executable'] ?? false),
                'confirmed' => (bool) ($proposal['confirmed'] ?? false),
                'status' => (string) ($proposal['status'] ?? 'proposed'),
            ];
        }, $actions);
    }

    private function validateToolProposal(string $tool, array $arguments, array $permissions, string $operation, array $proposal): void
    {
        if (! $this->tools->get($tool)) {
            throw new NexusAiTransportException('tool_not_found', 'La herramienta propuesta no existe: '.$tool.'.');
        }

        $allowed = array_keys(array_fill_keys($this->tools->definitions(), ''));
        if (isset($allowed[0])) {
            $registered = collect($this->tools->definitions())->pluck('name')->all();
            if (! in_array($tool, $registered, true)) {
                throw new NexusAiTransportException('tool_not_found', 'La herramienta propuesta no está registrada: '.$tool.'.');
            }
        }

        foreach (['permission', 'policy', 'restriction', 'weights', 'training'] as $reservedKey) {
            if (array_key_exists($reservedKey, $arguments)) {
                throw new NexusAiTransportException('protected_argument', 'La propuesta de herramienta intenta manipular un argumento protegido: '.$reservedKey.'.');
            }
        }

        $definition = collect($this->tools->definitions())->firstWhere('name', $tool);
        if (! is_array($definition)) {
            throw new NexusAiTransportException('tool_not_registered', 'La herramienta propuesta no está registrada: '.$tool.'.');
        }

        $declaredPermissions = array_values(array_filter((array) ($definition['permissions'] ?? []), 'is_string'));
        $effectivePermissions = array_values(array_unique(array_filter(array_merge($declaredPermissions, $permissions), 'is_string')));
        if ($operation === 'delete' && ! in_array('nexus.write', $effectivePermissions, true) && ! in_array('destructive_action', $effectivePermissions, true)) {
            throw new NexusAiTransportException('privilege_escalation', 'DELETE requiere permisos de destrucción explícitos.');
        }

        if (($proposal['source'] ?? 'nexus_ai_python') !== 'nexus_ai_python') {
            throw new NexusAiTransportException('invalid_proposal_origin', 'La propuesta de herramienta no tiene origen confiable.');
        }

        if (($proposal['executable'] ?? false) === true) {
            throw new NexusAiTransportException('non_executable_proposal', 'Python no puede ejecutar directamente la herramienta propuesta.');
        }
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

    private function executeProposedActions(array $response, array $payload, NexusRuntimeRequest $request, string $requestId): array
    {
        $actions = $response['proposed_actions'] ?? [];
        if (! is_array($actions) || $actions === []) {
            return $response;
        }

        $permissionManager = app(NexusPermissionManager::class);
        $securityBoundary = app(NexusSecurityBoundary::class);
        $registeredTools = array_values(array_map(
            static fn (array $tool): string => (string) ($tool['name'] ?? ''),
            $this->tools->definitions()
        ));

        $executed = [];
        foreach ($this->normalizeToolProposals($actions) as $proposal) {
            $toolName = $proposal['tool'];
            $tool = $this->tools->get($toolName);
            $correlationId = $proposal['correlation_id'] ?: $requestId;
            $arguments = $proposal['arguments'];

            $securityBoundary->assertToolCall($toolName, $arguments, $registeredTools);

            $effectivePermissions = $proposal['permissions'] !== []
                ? $proposal['permissions']
                : $tool->permissions();

            $permissionContext = new \App\Nexus\NexusToolContext(
                user: $request->user,
                source: 'nexus_ai_python',
                confirmed: (bool) ($proposal['confirmed'] ?? false),
                grantedPermissions: $payload['permissions'] ?? [],
                runId: null,
                projectId: $request->projectId,
                toolName: $toolName,
            );

            $auditId = $permissionManager->authorize(
                $toolName,
                $effectivePermissions,
                $arguments,
                $permissionContext,
                (bool) $proposal['requires_confirmation']
            );

            if ($auditId > 0 && $permissionManager->requiresApproval($toolName, $effectivePermissions, $permissionContext, (bool) $proposal['requires_confirmation'])) {
                $executed[] = [
                    'tool' => $toolName,
                    'status' => 'awaiting_confirmation',
                    'operation' => $proposal['operation'],
                    'correlation_id' => $correlationId,
                    'requires_confirmation' => true,
                    'source' => 'laravel_permission_manager',
                    'audit_id' => $auditId,
                ];
                continue;
            }

            $result = $this->tools->execute($toolName, $arguments, new \App\Nexus\NexusToolContext(
                user: $request->user,
                source: 'laravel_tool_registry',
                confirmed: (bool) ($proposal['confirmed'] ?? false),
                grantedPermissions: $effectivePermissions,
                runId: $auditId,
                projectId: $request->projectId,
                toolName: $toolName,
            ));

            $permissionManager->complete($auditId, $result->successful, [
                'result' => $result->toArray(),
                'origin' => 'laravel_tool_registry',
                'correlation_id' => $correlationId,
                'source' => 'nexus_ai_python',
            ]);

            $executed[] = [
                'tool' => $toolName,
                'status' => $result->successful ? 'completed' : 'failed',
                'operation' => $proposal['operation'],
                'correlation_id' => $correlationId,
                'result' => $result->toArray(),
                'requires_confirmation' => $proposal['requires_confirmation'],
                'source' => 'laravel_tool_registry',
                'audit_id' => $auditId,
                'origin' => 'laravel_tool_registry',
            ];
        }

        $response['proposed_actions'] = $executed;
        $response['tool_results'] = array_values(array_filter(array_map(
            static fn (array $entry): ?array => $entry['result'] ?? null,
            $executed
        )));

        return $response;
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
