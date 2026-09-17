<?php

namespace App\Console\Commands;

use App\Services\NexusProductionReadinessService;
use Illuminate\Console\Command;

final class Nexus40AuditCommand extends Command
{
    protected $signature = 'nexus:40-audit {--json : Imprimir el informe completo como JSON}';

    protected $description = 'Audita contratos y límites de integración de Nexus 3.0 sin ejecutar mutaciones';

    public function handle(NexusProductionReadinessService $audit): int
    {
        $report = $audit->audit(app(\App\Nexus\NexusToolRegistry::class));
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->line('Veredicto: '.$report['verdict']);
            $this->line('Autonomía: '.$report['autonomy_claim']);
            $this->line('Trace ID: '.$report['trace_id']);
            foreach ($report['blocking_findings'] as $finding) {
                $this->warn($finding);
            }
        }

        return $report['verdict'] === 'production_ready'
            ? self::SUCCESS
            : self::FAILURE;
    }
}
