<?php

namespace App\Services;

use App\Contracts\NexusModel;
use App\Exceptions\NexusModelException;
use App\Exceptions\NexusToolException;
use App\Nexus\NexusModelRequest;
use App\Nexus\NexusModelResponse;
use App\Nexus\NexusReasoningResult;
use App\Nexus\NexusToolRegistry;
use App\Nexus\NexusIdentity;
use Illuminate\Support\Facades\Log;
use Throwable;

class NexusReasoningService
{
    private readonly NexusCognitiveSecurityBoundary $boundary;

    public function __construct(
        private readonly NexusModel $model,
        private readonly NexusToolRegistry $tools,
        ?NexusCognitiveSecurityBoundary $boundary = null,
    ) {
        $this->boundary = $boundary ?? new NexusCognitiveSecurityBoundary($tools);
    }

    public function reason(
        string $message,
        array $context = [],
        array $history = [],
        array $toolResults = [],
    ): NexusReasoningResult {
        try {
            $response = $this->model->complete(new NexusModelRequest(
                identity: NexusIdentity::prompt(),
                message: $message,
                context: $context,
                tools: $this->tools->definitions(),
                history: $history,
                toolResults: $toolResults,
            ));

            $response = $this->validateResponse($response);

            return NexusReasoningResult::success($response);
        } catch (NexusModelException $exception) {
            return NexusReasoningResult::failure($exception->errorCode, $exception->getMessage());
        } catch (NexusToolException $exception) {
            return NexusReasoningResult::failure($exception->errorCode, $exception->getMessage());
        } catch (Throwable $exception) {
            Log::error('Nexus reasoning failed.', ['exception' => $exception]);

            return NexusReasoningResult::failure(
                'reasoning_failed',
                'Nexus no pudo interpretar la solicitud con el modelo configurado.'
            );
        }
    }

    private function validateResponse(NexusModelResponse $response): NexusModelResponse
    {
        if ($response->intent === '' || $response->message === '') {
            throw new NexusModelException(
                'El modelo devolvió una interpretación incompleta.',
                'invalid_model_response'
            );
        }

        $requiresConfirmation = $response->requiresConfirmation;

        foreach ($response->toolCalls as $call) {
            if (! is_array($call) || ! isset($call['name'], $call['arguments'])) {
                throw new NexusModelException(
                    'El modelo devolvió una llamada de herramienta inválida.',
                    'invalid_tool_call'
                );
            }

            $tool = $this->tools->get((string) $call['name']);
            if (! is_array($call['arguments'])) {
                throw new NexusModelException(
                    'Los argumentos de una herramienta deben ser un objeto.',
                    'invalid_tool_arguments'
                );
            }

            $requiresConfirmation = $requiresConfirmation || $tool->requiresConfirmation();
        }

        $boundaryErrors = $this->boundary->validateModelToolCalls($response->toolCalls, false);
        if ($boundaryErrors !== []) {
            throw new NexusModelException(implode('; ', $boundaryErrors), 'cognitive_boundary_violation');
        }

        return new NexusModelResponse(
            intent: $response->intent,
            message: $response->message,
            toolCalls: $response->toolCalls,
            requiresConfirmation: $requiresConfirmation,
            metadata: $response->metadata
        );
    }
}
