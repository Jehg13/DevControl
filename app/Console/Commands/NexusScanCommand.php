<?php

namespace App\Console\Commands;

use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolRegistry;
use Illuminate\Console\Command;
use Throwable;

class NexusScanCommand extends Command
{
    protected $signature = 'nexus:scan {--project= : ID del proyecto a revisar}';
    protected $description = 'Analiza DevControl localmente y persiste hallazgos sin modificar datos de negocio';

    public function handle(NexusToolRegistry $tools): int
    {
        try {
            $project = $this->option('project');
            if ($project !== null && (! ctype_digit((string) $project) || (int) $project < 1)) {
                $this->error('El proyecto debe ser un ID numérico positivo.');
                return self::INVALID;
            }
            $result = $tools->execute(
                'nexus.audit.scan',
                ['project_id' => $project ? (int) $project : null],
                new NexusToolContext(source: 'artisan', system: true)
            );

            if (! $result->successful) {
                $this->error($result->error ?? 'El escaneo no pudo completarse.');

                return self::FAILURE;
            }

            $this->info("Escaneo completado: {$result->data['detected']} hallazgos detectados, {$result->data['saved']} persistidos.");
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('No fue posible completar el escaneo: '.$exception->getMessage());
            return self::FAILURE;
        }
    }
}
