<?php

namespace App\Console\Commands;

use App\Services\NexusProactiveService;
use Illuminate\Console\Command;
use Throwable;

class NexusProactiveCommand extends Command
{
    protected $signature = 'nexus:proactive
        {--project= : ID del proyecto}
        {--silence= : ID de alerta a silenciar}
        {--minutes=60 : Minutos de silenciamiento}';

    protected $description = 'Revisa Nexus proactivamente y notifica solo alertas relevantes';

    public function handle(NexusProactiveService $service): int
    {
        try {
            if ($this->option('silence')) {
                $service->silence((int) $this->option('silence'), (int) $this->option('minutes'));
                $this->info('Alerta silenciada.');
                return self::SUCCESS;
            }
            $result = $service->run($this->option('project') ? (int) $this->option('project') : null);
            $this->info("Revisión proactiva: {$result['groups']} grupo(s), {$result['notified']} notificado(s), {$result['suppressed']} suprimido(s).");
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('La revisión proactiva falló: '.$exception->getMessage());
            return self::FAILURE;
        }
    }
}
