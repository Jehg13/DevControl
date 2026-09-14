<?php

namespace App\Services\Models;

use App\Contracts\NexusModel;
use App\Exceptions\NexusModelException;
use App\Nexus\NexusModelRequest;
use App\Nexus\NexusModelResponse;
use App\Nexus\NexusModelCapabilities;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class OpenAICompatibleNexusModel implements NexusModel
{
    public function capabilities(): NexusModelCapabilities
    {
        return new NexusModelCapabilities(
            textGeneration: true,
            structuredOutput: true,
            toolCalling: true,
        );
    }

    public function complete(NexusModelRequest $request): NexusModelResponse
    {
        $config = config('nexus.ai');
        $endpoint = rtrim((string) ($config['endpoint'] ?? ''), '/');
        $apiKey = (string) ($config['api_key'] ?? '');

        if (! $config['enabled'] || $endpoint === '' || $apiKey === '') {
            throw new NexusModelException(
                'El proveedor de modelo de Nexus no está configurado.',
                'model_not_configured'
            );
        }

        try {
            $response = Http::acceptJson()
                ->withToken($apiKey)
                ->timeout((int) ($config['timeout'] ?? 30))
                ->post($endpoint, [
                    'model' => $config['model'],
                    'temperature' => (float) ($config['temperature'] ?? 0.1),
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => $this->systemPrompt($request),
                        ],
                        [
                            'role' => 'user',
                            'content' => $request->message,
                        ],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            throw new NexusModelException(
                'No fue posible conectar con el proveedor del modelo.',
                'model_connection_failed'
            );
        }

        if ($response->failed()) {
            throw new NexusModelException(
                'El proveedor del modelo rechazó la solicitud.',
                'model_request_failed'
            );
        }

        $content = $response->json('choices.0.message.content');
        if (! is_string($content) || trim($content) === '') {
            throw new NexusModelException(
                'El proveedor devolvió una respuesta vacía.',
                'empty_model_response'
            );
        }

        $decoded = json_decode($this->stripCodeFence($content), true);
        if (! is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            throw new NexusModelException(
                'El modelo no devolvió JSON válido.',
                'invalid_model_json'
            );
        }

        return new NexusModelResponse(
            intent: (string) ($decoded['intent'] ?? ''),
            message: (string) ($decoded['message'] ?? ''),
            toolCalls: is_array($decoded['tool_calls'] ?? null) ? $decoded['tool_calls'] : [],
            requiresConfirmation: (bool) ($decoded['requires_confirmation'] ?? false),
            metadata: is_array($decoded['metadata'] ?? null) ? $decoded['metadata'] : [],
        );
    }

    private function systemPrompt(NexusModelRequest $request): string
    {
        return $request->identity."\n\n".
            "Interpreta la solicitud y devuelve exclusivamente este objeto JSON:\n".
            "{\n".
            '  "intent": "identificador_de_intencion",'."\n".
            '  "message": "respuesta breve para el usuario",'."\n".
            '  "tool_calls": [{"name": "nombre.exacto", "arguments": {}}],'."\n".
            '  "requires_confirmation": false,'."\n".
            '  "metadata": {"confidence": 0, "objective": "", "priority": 50, "subtasks": [{"id": "subtask-1", "title": "", "description": "", "goal": "", "dependencies": [], "required_tools": [], "priority": 50, "status": "pending", "result": null, "error": null, "attempts": 0, "started_at": null, "completed_at": null, "expected_result": "", "completion_criteria": ""}], "expected_result": "", "completion_criteria": "", "target_files": []}'."\n".
            "}\n\n".
            "No inventes herramientas ni argumentos. Si no corresponde una herramienta, devuelve tool_calls como []. ".
            "Las herramientas solo se describen; otra capa decidirá si se ejecutan.\n\n".
            "Contexto disponible:\n".json_encode($request->context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n".
            "Herramientas disponibles:\n".json_encode($request->tools, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n".
            "Historial y memoria relevante recuperados (el historial completo no se incluye):\n".json_encode($request->history, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n\n".
            "Resultados de herramientas de los pasos anteriores:\n".json_encode($request->toolResults, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function stripCodeFence(string $content): string
    {
        return trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content)) ?? $content);
    }
}
