<?php

namespace App\Console\Commands;

use App\Services\NexusAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

class NexusWatchCommand extends Command
{
    protected $signature = 'nexus:watch
        {--interval=10 : Segundos entre comprobaciones}
        {--silent : Ocultar el resumen cuando no haya cambios de archivos}
        {--once : Revisar una vez y finalizar}';

    protected $description = 'Vigila cambios locales y ejecuta el escaneo seguro de Nexus';

    public function handle(NexusAuditService $scanner): int
    {
        $interval = (int) $this->option('interval');

        if ($interval < 1 || $interval > 3600) {
            $this->error('El intervalo debe estar entre 1 y 3600 segundos.');

            return self::INVALID;
        }

        $knownFiles = [];

        do {
            $currentFiles = $this->snapshot();
            $isInitialScan = $knownFiles === [];
            $changedFiles = $isInitialScan ? [] : $this->changedFiles($knownFiles, $currentFiles);

            if ($isInitialScan) {
                $this->line('Escaneo inicial de Nexus...');
            } elseif ($changedFiles !== []) {
                $this->line('Cambios detectados: '.implode(', ', $changedFiles));
            } elseif (! $this->option('silent')) {
                $this->line('Revisión periódica de proyectos...');
            }

            try {
                $result = $scanner->scan();

                if ($isInitialScan || $changedFiles !== [] || ! $this->option('silent')) {
                    $this->info("Nexus revisó {$result['projects']} proyecto(s): {$result['detected']} hallazgo(s), {$result['saved']} persistido(s).");
                }
            } catch (Throwable $exception) {
                $this->error('El escaneo de Nexus falló: '.$exception->getMessage());
            }

            $knownFiles = $currentFiles;

            if (! $this->option('once')) {
                sleep($interval);
            }
        } while (! $this->option('once'));

        return self::SUCCESS;
    }

    private function snapshot(): array
    {
        $snapshot = [];

        foreach (config('nexus.watch_directories', ['app', 'routes', 'config', 'database', 'resources']) as $directory) {
            $path = base_path($directory);

            if (! File::isDirectory($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                if ($this->isIgnored($file->getRelativePathname())) {
                    continue;
                }

                $snapshot[$file->getRelativePathname()] = $file->getMTime().':'.$file->getSize();
            }
        }

        ksort($snapshot);

        return $snapshot;
    }

    private function changedFiles(array $before, array $after): array
    {
        $paths = array_unique(array_merge(array_keys($before), array_keys($after)));

        return array_values(array_filter($paths, function (string $path) use ($before, $after): bool {
            return ($before[$path] ?? null) !== ($after[$path] ?? null);
        }));
    }

    private function isIgnored(string $path): bool
    {
        $normalized = str_replace('\\', '/', strtolower($path));

        foreach (config('nexus.watch_excluded', ['.env', 'vendor/', 'storage/', 'bootstrap/cache/']) as $excluded) {
            $excluded = str_replace('\\', '/', strtolower($excluded));

            if ($normalized === $excluded || str_starts_with($normalized, $excluded)) {
                return true;
            }
        }

        return str_ends_with($normalized, '.key')
            || str_ends_with($normalized, '.pem')
            || str_ends_with($normalized, '.crt');
    }
}
