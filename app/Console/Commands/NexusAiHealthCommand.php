<?php

namespace App\Console\Commands;

use App\Services\NexusAiHealthService;
use Illuminate\Console\Command;

class NexusAiHealthCommand extends Command
{
    protected $signature = 'nexus:ai-health {--json : Imprimir el estado como JSON}';

    protected $description = 'Comprueba la disponibilidad del modelo local de Nexus AI';

    public function handle(NexusAiHealthService $health): int
    {
        $report = $health->check();
        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        } else {
            $this->line('Nexus AI: '.($report['enabled'] ? 'ENABLED' : 'DISABLED'));
            $this->line('Python: '.($report['python'] ? 'AVAILABLE' : 'UNAVAILABLE'));
            $this->line('Model: '.($report['model_loaded'] ? 'LOADED' : ($report['model_exists'] ? 'NOT LOADED' : 'MISSING')));
            $this->line('Tokenizer: '.($report['tokenizer_loaded'] ? 'LOADED' : ($report['tokenizer_exists'] ? 'NOT LOADED' : 'MISSING')));
            $this->line('Inference: '.($report['inference_ready'] ? 'READY' : 'NOT READY'));
            if (isset($report['error']['message'])) {
                $this->error($report['error']['message']);
            }
        }

        return $report['inference_ready'] ? self::SUCCESS : self::FAILURE;
    }
}
