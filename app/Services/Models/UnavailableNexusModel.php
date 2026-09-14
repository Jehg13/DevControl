<?php

namespace App\Services\Models;

use App\Contracts\NexusModel;
use App\Exceptions\NexusModelException;
use App\Nexus\NexusModelCapabilities;
use App\Nexus\NexusModelRequest;
use App\Nexus\NexusModelResponse;

final class UnavailableNexusModel implements NexusModel
{
    public function complete(NexusModelRequest $request): NexusModelResponse
    {
        throw new NexusModelException(
            'No hay un modelo de inferencia configurado. Configure un adaptador explícito para habilitar el razonamiento.',
            'model_unavailable'
        );
    }

    public function capabilities(): NexusModelCapabilities
    {
        return new NexusModelCapabilities();
    }
}
