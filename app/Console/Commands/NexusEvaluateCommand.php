<?php

namespace App\Console\Commands;

use App\Services\NexusEvaluationService;
use Illuminate\Console\Command;
use Throwable;

class NexusEvaluateCommand extends Command
{
    protected $signature = 'nexus:evaluate
        {--input= : Archivo JSON con respuestas agrupadas por versión}
        {--candidate-version=* : Versiones a incluir cuando se usa --input}
        {--manifest : Mostrar el catálogo y la rúbrica}
        {--output= : Guardar el resultado JSON en una ruta relativa al proyecto}';

    protected $description = 'Ejecuta benchmarks reproducibles de Nexus AI sin declarar versiones por impresión subjetiva';

    public function handle(NexusEvaluationService $evaluation): int
    {
        try {
            if ($this->option('manifest')) {
                $this->line(json_encode([
                    'benchmark_version' => '1.0',
                    'metrics' => ['exactness', 'errors', 'hallucinations', 'code_issues', 'diagnosis', 'tool_calling', 'permissions', 'memory', 'planning'],
                    'benchmarks' => $evaluation->benchmarks(),
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

                return self::SUCCESS;
            }

            $input = $this->option('input');
            if (! is_string($input) || $input === '') {
                $this->error('Proporciona --input=archivo.json o usa --manifest para consultar los benchmarks.');
                return self::INVALID;
            }

            $path = $this->safePath($input);
            $payload = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($payload)) {
                throw new \InvalidArgumentException('El JSON raíz debe ser un objeto de versiones.');
            }

            $versions = $this->option('candidate-version');
            if ($versions !== []) {
                $payload = array_intersect_key($payload, array_flip($versions));
            }
            if ($payload === []) {
                throw new \InvalidArgumentException('No hay versiones para evaluar.');
            }

            $result = $evaluation->compare($payload);
            $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            $this->line($json);

            if (is_string($this->option('output')) && $this->option('output') !== '') {
                $output = $this->safeOutputPath((string) $this->option('output'));
                file_put_contents($output, $json.PHP_EOL);
                $this->info('Resultado guardado en '.$this->option('output'));
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('La evaluación no pudo completarse: '.$exception->getMessage());
            return self::FAILURE;
        }
    }

    private function safePath(string $path): string
    {
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, ':')) {
            throw new \InvalidArgumentException('La ruta debe ser relativa y permanecer dentro del proyecto.');
        }

        $resolved = realpath(base_path($path));
        if ($resolved === false || ! str_starts_with($resolved, realpath(base_path()).DIRECTORY_SEPARATOR)) {
            throw new \InvalidArgumentException('La ruta no existe o está fuera del proyecto.');
        }

        return $resolved;
    }

    private function safeOutputPath(string $path): string
    {
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, ':')) {
            throw new \InvalidArgumentException('La ruta de salida debe ser relativa y permanecer dentro del proyecto.');
        }

        $project = realpath(base_path());
        $parent = realpath(dirname(base_path($path)));
        if ($project === false || $parent === false || ! str_starts_with($parent, $project.DIRECTORY_SEPARATOR)) {
            throw new \InvalidArgumentException('La carpeta de salida no existe o está fuera del proyecto.');
        }

        return $parent.DIRECTORY_SEPARATOR.basename($path);
    }
}
