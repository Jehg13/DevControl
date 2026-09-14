<?php

namespace App\Contracts;

use App\Nexus\NexusModelRequest;
use App\Nexus\NexusModelResponse;

interface NexusModel
{
    public function complete(NexusModelRequest $request): NexusModelResponse;
}
