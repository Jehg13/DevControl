<?php

namespace App\Console\Commands;

use App\Models\NexusCodeFile;
use App\Models\NexusGithubAnalysis;
use App\Models\Proyecto;
use App\Services\NexusGithubService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class NexusGithubAnalyzeCommand extends Command
{
    protected $signature = 'nexus:github-analyze
        {--project= : ID del proyecto}
        {--batch=25 : Archivos máximos por ejecución}';

    protected $description = 'Analiza repositorios GitHub completos por lotes persistentes';

    private const MAX_FILE_BYTES = 512000;
    private const EXCLUDED = ['.git/', 'vendor/', 'node_modules/', 'storage/', 'bootstrap/cache/'];

    public function handle(NexusGithubService $github): int
    {
        $query = NexusGithubAnalysis::query()->whereIn('status', ['pending', 'processing'])
            ->orderBy('id');
        if ($this->option('project') !== null) {
            $query->where('proyecto_id', (int) $this->option('project'));
        }
        $analysis = $query->first();
        if (! $analysis) {
            $this->info('No hay análisis de GitHub pendientes.');
            return self::SUCCESS;
        }

        $batch = max(1, min(100, (int) $this->option('batch')));
        $analysis->update([
            'status' => 'processing',
            'started_at' => $analysis->started_at ?: now(),
            'error' => null,
        ]);

        try {
            $project = $analysis->proyecto;
            if (! $analysis->files) {
                $files = collect($github->allFiles($project))
                    ->filter(fn (array $file): bool => $this->isSafeFile((string) ($file['path'] ?? '')))
                    ->values()->all();
                $analysis->update([
                    'files' => $files,
                    'total_files' => count($files),
                    'branch' => $project->integracionGithub?->rama_principal,
                ]);
            }

            $files = $analysis->files ?? [];
            $slice = array_slice($files, $analysis->cursor, $batch);
            $processed = $analysis->processed_files;
            $skipped = $analysis->skipped_files;
            foreach ($slice as $file) {
                $size = (int) ($file['size'] ?? 0);
                if ($size > self::MAX_FILE_BYTES) {
                    $skipped++;
                    continue;
                }
                $data = $github->file($project, (string) $file['path'], null, null, $analysis->branch);
                $content = $data['decoded_content'] ?? null;
                if (! is_string($content) || str_contains(substr($content, 0, 4096), "\0")) {
                    $skipped++;
                    continue;
                }
                NexusCodeFile::updateOrCreate(
                    ['proyecto_id' => $project->id, 'path' => $file['path']],
                    [
                        'fingerprint' => hash('sha256', $content),
                        'language' => strtolower(pathinfo($file['path'], PATHINFO_EXTENSION)) ?: 'text',
                        'analysis' => [
                            'bytes' => strlen($content),
                            'lines' => substr_count($content, "\n") + 1,
                            'github_sha' => $file['sha'] ?? null,
                        ],
                        'status' => 'active',
                        'invalidated_at' => null,
                    ]
                );
                $processed++;
            }

            $nextCursor = $analysis->cursor + count($slice);
            $complete = $nextCursor >= count($files);
            $analysis->update([
                'cursor' => $nextCursor,
                'processed_files' => $processed,
                'skipped_files' => $skipped,
                'status' => $complete ? 'completed' : 'processing',
                'finished_at' => $complete ? now() : null,
            ]);
            $project->integracionGithub?->update([
                'estado' => $complete ? 'analizado' : 'analizando',
                'ultimo_error' => null,
                'ultima_sincronizacion' => $complete ? now() : $project->integracionGithub->ultima_sincronizacion,
            ]);
            $this->line("{$analysis->id}: {$nextCursor}/".count($files));
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $analysis->update(['status' => 'failed', 'error' => $exception->getMessage()]);
            $analysis->proyecto?->integracionGithub?->update([
                'estado' => 'error',
                'ultimo_error' => 'El análisis de GitHub no pudo completarse.',
            ]);
            $this->error('El análisis falló: '.$exception->getMessage());
            return self::FAILURE;
        }
    }

    private function isSafeFile(string $path): bool
    {
        $path = strtolower(str_replace('\\', '/', ltrim($path, '/')));
        if ($path === '' || str_contains($path, '..') || preg_match('~(^|/)\.env(?:\.|$)~', $path)) {
            return false;
        }
        foreach (self::EXCLUDED as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }
        return ! preg_match('~\.(png|jpe?g|gif|webp|ico|pdf|zip|gz|tar|7z|exe|dll|bin|woff2?|ttf|mp[34]|mov)$~', $path);
    }
}
