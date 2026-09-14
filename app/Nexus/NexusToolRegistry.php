<?php

namespace App\Nexus;

use App\Contracts\NexusTool;
use App\Exceptions\NexusToolException;
use Illuminate\Support\Facades\Log;
use Throwable;

final class NexusToolRegistry
{
    /** @var array<string, NexusTool> */
    private array $tools = [];

    /** @param iterable<NexusTool> $tools */
    public function __construct(iterable $tools = [])
    {
        foreach ($tools as $tool) {
            $this->register($tool);
        }
    }

    public function register(NexusTool $tool): self
    {
        if (isset($this->tools[$tool->name()])) {
            throw new NexusToolException(
                "La herramienta [{$tool->name()}] ya está registrada.",
                'duplicate_tool'
            );
        }

        $this->tools[$tool->name()] = $tool;

        return $this;
    }

    /** @return array<int, array<string, mixed>> */
    public function definitions(): array
    {
        return array_values(array_map(
            fn (NexusTool $tool): array => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->parameters(),
                'permissions' => $tool->permissions(),
                'risk_level' => app(\App\Services\NexusPermissionManager::class)->risk($tool->name(), $tool->permissions()),
                'requires_confirmation' => $tool->requiresConfirmation(),
            ],
            $this->tools
        ));
    }

    public function get(string $name): NexusTool
    {
        if (! isset($this->tools[$name])) {
            throw new NexusToolException("La herramienta [{$name}] no está registrada.", 'tool_not_found');
        }

        return $this->tools[$name];
    }

    public function execute(string $name, array $parameters = [], ?NexusToolContext $context = null): NexusToolResult
    {
        try {
            return $this->get($name)->execute($parameters, $context ?? new NexusToolContext());
        } catch (NexusToolException $exception) {
            return NexusToolResult::failure(
                $exception->errorCode,
                $exception->getMessage(),
                $exception instanceof \App\Exceptions\NexusToolValidationException
                    ? ['fields' => $exception->errors]
                    : []
            );
        } catch (Throwable $exception) {
            Log::error('Nexus tool execution failed.', [
                'tool' => $name,
                'exception' => $exception,
            ]);

            return NexusToolResult::failure(
                'execution_failed',
                'La herramienta no pudo completar la operación.'
            );
        }
    }
}
