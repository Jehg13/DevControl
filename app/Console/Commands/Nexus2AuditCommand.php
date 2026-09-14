<?php

namespace App\Console\Commands;

use App\Services\Nexus2AuditService;
use Illuminate\Console\Command;

class Nexus2AuditCommand extends Command
{
    protected $signature = 'nexus:2-audit {--json : Imprimir el informe como JSON}';

    protected $description = 'Audita Nexus AI 2.0 y no declara preparación sin evidencia local';

    public function handle(Nexus2AuditService $audit): int
    {
        $report = $audit->audit();
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->info("Veredicto: {$report['verdict']}");
            foreach ($report['blocking_findings'] as $finding) {
                $this->warn($finding);
            }
            $this->line('Componentes presentes: '.count(array_filter($report['architecture'])).'/'.count($report['architecture']));
            $this->line('Artefactos locales completos: '.($report['capabilities']['local_model_artifacts'] ? 'sí' : 'no'));
            $this->line('Tool calling local: '.($report['capabilities']['local_tool_calling'] ? 'sí' : 'no'));
        }

        return $report['verdict'] === 'ready_for_end_to_end_validation'
            ? self::SUCCESS
            : self::FAILURE;
    }
}
