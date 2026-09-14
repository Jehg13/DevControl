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
        $run = NexusRun::create([
            'usuario_id' => $userId,
            'nexus_conversation_id' => $conversation->id,
            'source' => $source,
            'message' => $message,
            'status' => 'running',
            'context' => $context,
        ]);
        $this->memory->recordMessage($conversation, 'user', $message, ['run_id' => $run->id]);

        $toolResults = [];
        $executedCalls = [];
        $lastResponse = null;
        $maxSteps = max(1, (int) config('nexus.ai.max_steps', 5));

        try {
            for ($step = 1; $step <= $maxSteps; $step++) {
                $run->update(['steps' => $step]);
                $reasoning = $this->reasoning->reason(
                    $message,
                    $retrievedContext,
                    [],
                    $toolResults
                );

                if (! $reasoning->successful) {
                    return $this->fail($run, $reasoning->errorCode ?? 'reasoning_failed', $reasoning->error ?? 'Razonamiento fallido.');
                }

                $lastResponse = $reasoning->response;
                if ($lastResponse->toolCalls === []) {
                    $this->memory->recordMessage($conversation, 'assistant', $lastResponse->message, ['run_id' => $run->id]);
                    return $this->complete($run, $lastResponse->toArray());
                }

                $requiresConfirmation = collect($lastResponse->toolCalls)
                    ->map(fn (array $call) => $this->tools->get($call['name']))
                    ->contains(fn ($tool) => $tool->requiresConfirmation());

                if ($requiresConfirmation && ! $confirmed) {
                    foreach ($lastResponse->toolCalls as $call) {
                        $this->recordPendingCall($run, $call);
                    }

                    return $this->complete($run, [
                        'status' => 'confirmation_required',
                        'reasoning' => $lastResponse->toArray(),
                    ], 'awaiting_confirmation');
                }

                foreach ($lastResponse->toolCalls as $call) {
                    $signature = hash('sha256', json_encode([
                        $call['name'],
                        $call['arguments'],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

                    if (isset($executedCalls[$signature])) {
                        return $this->fail($run, 'duplicate_tool_call', 'El modelo solicitó repetidamente la misma herramienta y Nexus detuvo la ejecución.');
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
                    $toolResults[] = [
                        'tool' => $call['name'],
                        'arguments' => $call['arguments'],
                        'result' => $result->toArray(),
                    ];

                    if (! $result->successful && $result->errorCode === 'confirmation_required') {
                        return $this->complete($run, [
                            'status' => 'confirmation_required',
                            'reasoning' => $lastResponse->toArray(),
                            'tool_results' => $toolResults,
                        ], 'awaiting_confirmation');
                    }
                }
            }

            return $this->fail($run, 'max_steps_exceeded', 'Nexus alcanzó el límite de pasos sin obtener una respuesta final.');
        } catch (Throwable $exception) {
            Log::error('Nexus execution failed.', ['run_id' => $run->id, 'exception' => $exception]);

            return $this->fail($run, 'execution_failed', 'Nexus no pudo completar la ejecución.');
        }
    }

    private function complete(NexusRun $run, array $result, string $status = 'completed'): NexusRun
    {
        $run->update(['status' => $status, 'result' => $result]);

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

    private function fail(NexusRun $run, string $code, string $message): NexusRun
    {
        $run->update(['status' => 'failed', 'error' => "{$code}: {$message}"]);

        return $run->fresh('toolCalls');
    }
}
