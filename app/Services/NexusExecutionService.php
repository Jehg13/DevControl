<?php

namespace App\Services;

use App\Models\NexusRun;
use App\Models\NexusToolCall;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolRegistry;
use Illuminate\Support\Facades\Log;
use Throwable;

class NexusExecutionService
{
    public function __construct(
        private readonly NexusReasoningService $reasoning,
        private readonly NexusToolRegistry $tools,
        private readonly NexusMemoryService $memory,
        private readonly NexusStateService $state,
        private readonly NexusPlanService $plans,
        private readonly NexusReflectionService $reflection,
    ) {
    }

    public function execute(
        string $message,
        array $context = [],
        array $history = [],
        ?int $userId = null,
        string $source = 'application',
        bool $confirmed = false,
        ?string $conversationKey = null,
    ): NexusRun {
        $conversation = $this->memory->conversation(
            $conversationKey ?? 'run-'.$userId.'-'.hash('sha256', $message),
            $userId
        );
        $retrievedContext = $this->memory->relevantContext($conversation, $message, $context);
        $internalState = $this->state->initialize($message, $context);
        $internalState = $this->state->includeRetrievedContext($internalState, $retrievedContext);
        $run = NexusRun::create([
            'usuario_id' => $userId,
            'nexus_conversation_id' => $conversation->id,
            'source' => $source,
            'message' => $message,
            'status' => 'running',
            'context' => $context,
            'internal_state' => $internalState,
        ]);
        $this->memory->recordMessage($conversation, 'user', $message, ['run_id' => $run->id]);

        $toolResults = [];
        $executedCalls = [];
        $preflight = $this->preflightProgrammingTask($run, $message, $context, $userId, $source, $internalState);
        if ($preflight !== null) {
            $toolResults[] = $preflight['result'];
            $executedCalls[$preflight['signature']] = true;
            $internalState = $preflight['state'];
            $this->state->persist($run, $internalState);
        }
        $lastResponse = null;
        $plan = null;
        $maxSteps = max(1, (int) config('nexus.ai.max_steps', 5));

        try {
            for ($step = 1; $step <= $maxSteps; $step++) {
                $run->update(['steps' => $step]);
                $retrievedContext['internal_state'] = $internalState;
                $reasoning = $this->reasoning->reason(
                    $message,
                    $retrievedContext,
                    [],
                    $toolResults
                );

                if (! $reasoning->successful) {
                    return $this->fail(
                        $run,
                        $reasoning->errorCode ?? 'reasoning_failed',
                        $reasoning->error ?? 'Razonamiento fallido.',
                        $internalState,
                        $conversation
                    );
                }

                $lastResponse = $reasoning->response;
                $internalState = $this->state->afterReasoning($internalState, $lastResponse->toArray(), $step);
                $plan = $this->plans->createOrUpdate($run, $lastResponse->metadata, $message) ?? $plan;
                if ($plan) {
                    $internalState['plan'] = $plan->toArray();
                }
                $this->state->persist($run, $internalState);
                if ($lastResponse->toolCalls === []) {
                    $this->memory->recordMessage($conversation, 'assistant', $lastResponse->message, ['run_id' => $run->id]);
                    $this->memory->promoteInteraction(
                        $conversation,
                        $message,
                        $lastResponse->message,
                        $context
                    );
                    $internalState['next_action'] = 'completed';
                    if ($plan) {
                        $plan = $this->plans->complete($plan);
                        $internalState['plan'] = $plan->toArray();
                    }
                    return $this->complete($run, $lastResponse->toArray(), 'completed', $internalState, $conversation);
                }

                $requiresConfirmation = collect($lastResponse->toolCalls)
                    ->map(fn (array $call) => $this->tools->get($call['name']))
                    ->contains(fn ($tool) => $tool->requiresConfirmation());

                if ($requiresConfirmation && ($internalState['plan'] ?? []) === []) {
                    $internalState['phase'] = 'planning';
                    $internalState['next_action'] = 'create_plan_before_changes';

                    return $this->fail(
                        $run,
                        'plan_required',
                        'Nexus debe crear un plan y definir los archivos objetivo antes de ejecutar cambios.',
                        $internalState,
                        $conversation
                    );
                }

                if ($requiresConfirmation && ! $confirmed) {
                    foreach ($lastResponse->toolCalls as $call) {
                        $this->recordPendingCall($run, $call);
                    }

                    $internalState['next_action'] = 'await_confirmation';
                    return $this->complete($run, [
                        'status' => 'confirmation_required',
                        'reasoning' => $lastResponse->toArray(),
                    ], 'awaiting_confirmation', $internalState, $conversation);
                }

                foreach ($lastResponse->toolCalls as $call) {
                    $signature = hash('sha256', json_encode([
                        $call['name'],
                        $call['arguments'],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                    if (isset($executedCalls[$signature])) {
                        return $this->fail(
                            $run,
                            'duplicate_tool_call',
                            'El modelo solicitó repetidamente la misma herramienta y Nexus detuvo la ejecución.',
                            $internalState,
                            $conversation
                        );
                    }

                    $executedCalls[$signature] = true;
                    $tool = $this->tools->get($call['name']);
                    $toolCall = NexusToolCall::create([
                        'nexus_run_id' => $run->id,
                        'sequence' => $run->toolCalls()->count() + 1,
                        'tool_name' => $call['name'],
                        'arguments' => $call['arguments'],
                        'status' => 'running',
                        'started_at' => now(),
                    ]);

                    $result = $this->tools->execute(
                        $call['name'],
                        $call['arguments'],
                        new NexusToolContext(
                            user: $userId ? \App\Models\User::find($userId) : null,
                            source: $source,
                            confirmed: $confirmed
                        )
                    );

                    $toolCall->update([
                        'status' => $result->successful ? 'succeeded' : 'failed',
                        'result' => $result->toArray(),
                        'error' => $result->successful ? null : $result->error,
                        'finished_at' => now(),
                    ]);
                    $internalState = $this->state->afterTool(
                        $internalState,
                        $call['name'],
                        $result->toArray(),
                        $step
                    );
                    if ($plan) {
                        $plan = $this->plans->updateAfterAction($plan, $call['name'], $result->toArray());
                        $internalState['plan'] = $plan->toArray();
                    }
                    $this->state->persist($run, $internalState);
                    $toolResults[] = [
                        'tool' => $call['name'],
                        'arguments' => $call['arguments'],
                        'result' => $result->toArray(),
                    ];

                    if (! $result->successful && $result->errorCode === 'confirmation_required') {
                        $internalState['next_action'] = 'await_confirmation';
                        return $this->complete($run, [
                            'status' => 'confirmation_required',
                            'reasoning' => $lastResponse->toArray(),
                            'tool_results' => $toolResults,
                        ], 'awaiting_confirmation', $internalState, $conversation);
                    }
                }
            }

            return $this->fail($run, 'max_steps_exceeded', 'Nexus alcanzó el límite de pasos sin obtener una respuesta final.', $internalState, $conversation);
        } catch (Throwable $exception) {
            Log::error('Nexus execution failed.', ['run_id' => $run->id, 'exception' => $exception]);

            return $this->fail($run, 'execution_failed', 'Nexus no pudo completar la ejecución.', $internalState ?? [], $conversation);
        }
    }

    private function preflightProgrammingTask(
        NexusRun $run,
        string $message,
        array $context,
        ?int $userId,
        string $source,
        array $state,
    ): ?array {
        if (! preg_match('/\b(código|codigo|programa|programar|bug|error|función|funcion|clase|archivo|feature|refactor|implementar|modificar)\b/iu', $message)) {
            return null;
        }

        $arguments = [
            'project_id' => $context['project_id'] ?? $context['proyecto_id'] ?? null,
            'include_documentation' => true,
        ];
        $signature = hash('sha256', json_encode(['nexus.code.analyze', $arguments], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $toolCall = NexusToolCall::create([
            'nexus_run_id' => $run->id,
            'sequence' => 1,
            'tool_name' => 'nexus.code.analyze',
            'arguments' => $arguments,
            'status' => 'running',
            'started_at' => now(),
        ]);
        $result = $this->tools->execute(
            'nexus.code.analyze',
            $arguments,
            new NexusToolContext(
                user: $userId ? \App\Models\User::find($userId) : null,
                source: $source,
                confirmed: true
            )
        );
        $toolCall->update([
            'status' => $result->successful ? 'succeeded' : 'failed',
            'result' => $result->toArray(),
            'error' => $result->successful ? null : $result->error,
            'finished_at' => now(),
        ]);

        return [
            'signature' => $signature,
            'result' => [
                'tool' => 'nexus.code.analyze',
                'arguments' => $arguments,
                'result' => $result->toArray(),
            ],
            'state' => $this->state->afterTool($state, 'nexus.code.analyze', $result->toArray(), 0, 'plan_task'),
        ];
    }

    private function complete(NexusRun $run, array $result, string $status = 'completed', ?array $state = null, ?\App\Models\NexusConversation $conversation = null): NexusRun
    {
        $run->update(['status' => $status, 'result' => $result]);
        if ($conversation && $state !== null) {
            $reflection = $this->reflection->reflect($run->fresh(), $state, $result, $conversation);
            $state['reflection'] = $reflection;
        }
        if ($state !== null) {
            $this->state->persist($run, $state);
        }

        return $run->fresh('toolCalls');
    }

    private function recordPendingCall(NexusRun $run, array $call): void
    {
        NexusToolCall::create([
            'nexus_run_id' => $run->id,
            'sequence' => $run->toolCalls()->count() + 1,
            'tool_name' => $call['name'],
            'arguments' => $call['arguments'],
            'status' => 'awaiting_confirmation',
        ]);
    }

    private function fail(NexusRun $run, string $code, string $message, array $state = [], ?\App\Models\NexusConversation $conversation = null): NexusRun
    {
        $run->update(['status' => 'failed', 'error' => "{$code}: {$message}"]);
        if ($conversation && $state !== []) {
            $reflection = $this->reflection->reflect($run->fresh(), $state, [
                'status' => 'failed',
                'error' => ['code' => $code, 'message' => $message],
            ], $conversation);
            $state['reflection'] = $reflection;
        }
        if ($state !== []) {
            $this->state->persist($run, $this->state->markFailure($state, $message));
        }

        return $run->fresh('toolCalls');
    }
}
