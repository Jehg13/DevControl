<?php

namespace App\Console\Commands;

use App\Services\NexusAuditService;
use Illuminate\Console\Command;
use Throwable;

class NexusScanCommand extends Command
{
    protected $signature = 'nexus:scan {--project= : ID del proyecto a revisar}';
    protected $description = 'Analiza DevControl localmente y persiste hallazgos sin modificar datos de negocio';

    public function handle(NexusAuditService $scanner): int
    {
        try {
            $project = $this->option('project');
            if ($project !== null && (! ctype_digit((string) $project) || (int) $project < 1)) {
                $this->error('El proyecto debe ser un ID numérico positivo.');
                return self::INVALID;
            }
            $result = $scanner->scan($project ? (int) $project : null);
            $this->info("Escaneo completado: {$result['detected']} hallazgos detectados, {$result['saved']} persistidos.");
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('No fue posible completar el escaneo: '.$exception->getMessage());
            return self::FAILURE;
        }
    }
}
