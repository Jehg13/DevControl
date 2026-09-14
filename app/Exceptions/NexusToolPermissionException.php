<?php

namespace App\Exceptions;

class NexusToolPermissionException extends NexusToolException
{
    public function __construct(string $message = 'No tienes permisos para ejecutar esta herramienta.')
    {
        parent::__construct($message, 'permission_denied');
    }
}
