<?php

namespace App\Services;

use App\Contracts\NexusModel;
use App\Nexus\NexusModelCapabilities;
use App\Nexus\NexusModelRequest;
use App\Nexus\NexusModelResponse;

final class NexusInferenceEngine implements NexusModel
{
    public function __construct(private readonly NexusModel $adapter)
    {
    }

    public function complete(NexusModelRequest $request): NexusModelResponse
    {
        return $this->adapter->complete($request);
    }

    public function capabilities(): NexusModelCapabilities
    {
        if (method_exists($this->adapter, 'capabilities')) {
            return $this->adapter->capabilities();
        }

        return new NexusModelCapabilities(
            textGeneration: true,
            structuredOutput: true,
            toolCalling: true,
        );
    }
}
