<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use SplFileInfo;

final class NexusPhpIntelligenceService
{
    private const EXCLUDED_DIRECTORIES = ['.git', 'vendor', 'node_modules', 'storage', 'bootstrap/cache'];

    /**
     * Produce a read-only, evidence-based report for advanced PHP runtime and framework risks.
     *
     * @return array<string, mixed>
     */
    public function diagnose(?string $relativePath = null, int $maxFiles = 200): array
    {
        $root = $this->resolveRoot($relativePath);
        $files = $this->phpFiles($root, $maxFiles);
        $findings = [];
        $signals = [];

        foreach ($files as $file) {
            $path = $this->relativePath($file, $root);
            $content = File::get($file->getPathname());
            $fileSignals = $this->signals($content);
            $signals[$path] = $fileSignals;
            $findings = array_merge($findings, $this->findings($path, $content, $fileSignals));
        }

        $composer = $this->composer($root);
        $findings = array_merge($findings, $this->composerFindings($composer));
        $findings = array_merge($findings, $this->runtimeFindings());

        return [
            'scope' => $relativePath ?: '.',
            'files_analyzed' => count($files),
            'categories' => [
                'memory', 'references', 'autoloading', 'composer', 'psr', 'spl',
                'reflection', 'attributes', 'serialization', 'streams', 'processes',
                'cli', 'environment', 'performance', 'opcache', 'error_handling',
            ],
            'runtime' => $this->runtime(),
            'composer' => $composer,
            'signals' => $signals,
            'findings' => $this->sortFindings($findings),
            'summary' => $this->summary($findings),
            'read_only' => true,
        ];
    }

    private function resolveRoot(?string $relativePath): string
    {
        $relativePath = trim((string) $relativePath);
        if ($relativePath === '' || str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            return base_path();
        }

        $root = realpath(base_path($relativePath));
        $base = realpath(base_path());
        if ($root === false || $base === false || ($root !== $base && ! str_starts_with($root, $base.DIRECTORY_SEPARATOR))) {
            throw new \InvalidArgumentException('La ruta de diagnóstico debe estar dentro del proyecto.');
        }

        return $root;
    }

    /** @return array<int, SplFileInfo> */
    private function phpFiles(string $root, int $maxFiles): array
    {
        $maxFiles = max(1, min($maxFiles, 500));
        if (is_file($root)) {
            return strtolower(pathinfo($root, PATHINFO_EXTENSION)) === 'php' ? [new SplFileInfo($root)] : [];
        }

        return collect(File::allFiles($root))
            ->reject(fn (SplFileInfo $file): bool => $this->excluded($file, $root))
            ->filter(fn (SplFileInfo $file): bool => strtolower($file->getExtension()) === 'php' && $file->getSize() <= 1000000)
            ->take($maxFiles)
            ->values()
            ->all();
    }

    private function excluded(SplFileInfo $file, string $root): bool
    {
        $relative = str_replace('\\', '/', ltrim(str_replace($root, '', $file->getPathname()), '/\\'));

        return collect(self::EXCLUDED_DIRECTORIES)->contains(
            fn (string $directory): bool => $relative === $directory || str_starts_with($relative, $directory.'/')
        );
    }

    private function relativePath(SplFileInfo $file, string $root): string
    {
        if (method_exists($file, 'getRelativePathname')) {
            return str_replace('\\', '/', $file->getRelativePathname());
        }

        return str_replace('\\', '/', ltrim(str_replace($root, '', $file->getPathname()), '/\\'));
    }

    /** @return array<string, bool|int> */
    private function signals(string $content): array
    {
        return [
            'memory' => preg_match('/\b(?:memory_get_usage|memory_get_peak_usage|ini_set\s*\(\s*[\'"]memory_limit)/', $content) === 1,
            'references' => preg_match('/(?:^|[^\w])&\s*\$|function\s*&\s*[A-Za-z_]/', $content) === 1,
            'autoloading' => preg_match('/\b(?:spl_autoload_register|__autoload|class_alias)\b/', $content) === 1,
            'psr' => preg_match('/^\s*namespace\s+[A-Za-z_][\w\\\\]*\s*;/m', $content) === 1,
            'spl' => preg_match('/\b(?:SplFileInfo|ArrayObject|SplPriorityQueue|DirectoryIterator|RecursiveDirectoryIterator)\b/', $content) === 1,
            'reflection' => preg_match('/\bReflection(?:Class|Method|Function|Property|Attribute)\b/', $content) === 1,
            'attributes' => preg_match('/#\[\s*[A-Za-z_\\\\]/', $content) === 1,
            'serialization' => preg_match('/\b(?:serialize|unserialize|__serialize|__unserialize)\s*\(/', $content) === 1,
            'streams' => preg_match('/\b(?:fopen|fread|fwrite|stream_context_create|php:\/\/|file_get_contents)\b/', $content) === 1,
            'processes' => preg_match('/\b(?:proc_open|proc_close|shell_exec|passthru|popen|exec)\s*\(/', $content) === 1,
            'cli' => preg_match('/\b(?:\$argv|\$argc|STDIN|STDOUT|Symfony\\\\Component\\\\Console)\b/', $content) === 1,
            'environment' => preg_match('/\b(?:getenv|putenv|\$_ENV|\$_SERVER)\b/', $content) === 1,
            'performance' => preg_match('/\b(?:usleep|sleep|file_get_contents|json_decode|array_map|collect)\b/', $content) === 1,
            'error_handling' => preg_match('/\b(?:set_error_handler|set_exception_handler|try\s*\{|catch\s*\(|error_log|trigger_error)\b/', $content) === 1,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function findings(string $path, string $content, array $signals): array
    {
        $findings = [];
        $add = function (string $category, string $severity, string $code, string $message, string $recommendation, ?int $line = null) use (&$findings, $path): void {
            $findings[] = compact('category', 'severity', 'code', 'message', 'recommendation') + ['file' => $path, 'line' => $line];
        };
        $line = fn (string $needle): ?int => (($position = strpos($content, $needle)) === false ? null : substr_count(substr($content, 0, $position), "\n") + 1);

        if (preg_match('/\bunserialize\s*\(\s*\$_(?:GET|POST|REQUEST|COOKIE|SERVER|ENV)/', $content)) {
            $add('serialization', 'critical', 'unsafe_unserialize_input', 'Se deserializa entrada externa sin una lista de clases permitidas.', 'Usa JSON o unserialize($value, ["allowed_classes" => false]) y valida el origen.', $line('unserialize'));
        }
        if (preg_match('/\b(?:shell_exec|passthru|exec|system|proc_open)\s*\([^)]*\$/', $content)) {
            $add('processes', 'high', 'dynamic_process_input', 'Se construye un proceso con datos dinámicos; puede habilitar inyección de comandos.', 'Evita el shell, usa escapeshellarg/escapeshellcmd y una lista de argumentos permitidos.', $line('exec'));
        }
        if ($signals['references']) {
            $add('references', 'medium', 'mutable_reference', 'El código usa referencias PHP, que pueden crear alias mutables difíciles de rastrear.', 'Limita las referencias a APIs internas bien documentadas y evita devolver referencias desde servicios.', $line('&$'));
        }
        if (preg_match('/\bfile_get_contents\s*\(\s*\$|fopen\s*\(\s*\$/', $content)) {
            $add('streams', 'medium', 'unchecked_stream_input', 'La ruta de stream procede de una variable y requiere validación, límites y gestión explícita de errores.', 'Valida allowlists de rutas, comprueba false y usa límites de tamaño/timeout.', $line('file_get_contents'));
        }
        if (preg_match('/\b(?:memory_get_usage|memory_get_peak_usage)\b/', $content) && ! preg_match('/\bmemory_get_peak_usage\b/', $content)) {
            $add('memory', 'low', 'missing_peak_memory_observation', 'Se observa memoria actual pero no el pico de memoria de la operación.', 'Registra memory_get_peak_usage(true) en trabajos por lotes o CLI.', $line('memory_get_usage'));
        }
        if (preg_match('/\bcatch\s*\(\s*\\\\?Throwable\s+\$\w+\s*\)\s*\{\s*\}/s', $content)) {
            $add('error_handling', 'high', 'empty_throwable_handler', 'Un catch de Throwable vacío oculta fallos y dificulta el diagnóstico.', 'Registra el contexto y relanza o devuelve un error explícito.', $line('catch'));
        }
        if (preg_match('/\bset_error_handler\s*\(/', $content) && ! preg_match('/\brestore_error_handler\s*\(/', $content)) {
            $add('error_handling', 'medium', 'unrestored_error_handler', 'Se instala un handler global sin restaurarlo.', 'Restaura el handler en finally para no alterar solicitudes posteriores.', $line('set_error_handler'));
        }
        if (preg_match('/\b(?:proc_open|shell_exec|passthru|exec)\s*\(/', $content) && ! preg_match('/\bPHP_SAPI\b|\bapp\(\s*[\'"]request\b/', $content)) {
            $add('cli', 'low', 'process_context_unknown', 'Se ejecutan procesos sin evidencia de una política explícita para CLI/HTTP.', 'Define timeout, cancelación y permisos distintos para CLI y peticiones web.', $line('proc_open'));
        }
        if ($signals['attributes'] && ! $signals['reflection']) {
            $add('attributes', 'low', 'attributes_without_reflection_path', 'Hay atributos declarados, pero no se observa el flujo que los descubre o valida.', 'Verifica ReflectionAttribute::getAttributes y valida argumentos antes de instanciar.', $line('#['));
        }

        return $findings;
    }

    /** @return array<string, mixed> */
    private function composer(string $root): array
    {
        $path = $root.DIRECTORY_SEPARATOR.'composer.json';
        if (! is_file($path)) {
            return ['present' => false, 'autoload' => [], 'require' => []];
        }
        $decoded = json_decode(File::get($path), true);
        if (! is_array($decoded)) {
            return ['present' => true, 'valid' => false, 'autoload' => [], 'require' => []];
        }

        return [
            'present' => true,
            'valid' => true,
            'name' => $decoded['name'] ?? null,
            'require' => $decoded['require'] ?? [],
            'autoload' => $decoded['autoload'] ?? [],
            'autoload_dev' => $decoded['autoload-dev'] ?? [],
            'platform_php' => data_get($decoded, 'config.platform.php'),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function composerFindings(array $composer): array
    {
        if (! ($composer['present'] ?? false)) {
            return [[
                'category' => 'composer', 'severity' => 'medium', 'code' => 'composer_manifest_missing',
                'message' => 'No se encontró composer.json en el alcance analizado.',
                'recommendation' => 'Declara dependencias y autoload PSR-4 en Composer para reproducibilidad.',
                'file' => null, 'line' => null,
            ]];
        }
        if (($composer['valid'] ?? true) === false) {
            return [[
                'category' => 'composer', 'severity' => 'high', 'code' => 'invalid_composer_json',
                'message' => 'composer.json no contiene JSON válido.', 'recommendation' => 'Corrige el manifiesto antes de ejecutar composer install.',
                'file' => 'composer.json', 'line' => null,
            ]];
        }

        $autoload = (array) ($composer['autoload'] ?? []);
        if ($autoload === []) {
            return [[
                'category' => 'autoloading', 'severity' => 'medium', 'code' => 'autoload_not_declared',
                'message' => 'Composer no declara reglas de autoload para producción.',
                'recommendation' => 'Añade autoload.psr-4 o autoload.classmap y regenera vendor/autoload.php.',
                'file' => 'composer.json', 'line' => null,
            ]];
        }

        return [];
    }

    /** @return array<string, mixed> */
    private function runtime(): array
    {
        return [
            'php_version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'memory_limit' => ini_get('memory_limit') ?: null,
            'max_execution_time' => ini_get('max_execution_time') ?: null,
            'display_errors' => ini_get('display_errors') ?: null,
            'log_errors' => ini_get('log_errors') ?: null,
            'opcache' => [
                'available' => function_exists('opcache_get_status'),
                'enabled' => filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
                'cli_enabled' => filter_var(ini_get('opcache.enable_cli'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE),
            ],
            'extensions' => array_values(array_intersect(['opcache', 'SPL', 'Reflection', 'json', 'pcntl'], get_loaded_extensions())),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function runtimeFindings(): array
    {
        $findings = [];
        if (PHP_SAPI !== 'cli' && ini_get('display_errors')) {
            $findings[] = ['category' => 'error_handling', 'severity' => 'high', 'code' => 'display_errors_in_production', 'message' => 'display_errors está habilitado fuera de CLI.', 'recommendation' => 'Desactiva display_errors en producción y conserva log_errors.', 'file' => null, 'line' => null];
        }
        if (function_exists('opcache_get_status') && ! filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOL)) {
            $findings[] = ['category' => 'opcache', 'severity' => 'medium', 'code' => 'opcache_disabled', 'message' => 'OPcache está disponible pero deshabilitado.', 'recommendation' => 'Activa OPcache en producción y mide hit rate, memoria y reinicios.', 'file' => null, 'line' => null];
        }
        return $findings;
    }

    private function sortFindings(array $findings): array
    {
        $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        usort($findings, fn (array $a, array $b): int => ($rank[$a['severity']] ?? 9) <=> ($rank[$b['severity']] ?? 9));
        return $findings;
    }

    private function summary(array $findings): array
    {
        $counts = array_fill_keys(['critical', 'high', 'medium', 'low'], 0);
        foreach ($findings as $finding) {
            if (isset($counts[$finding['severity']])) {
                $counts[$finding['severity']]++;
            }
        }
        return ['total' => count($findings), 'by_severity' => $counts];
    }
}
