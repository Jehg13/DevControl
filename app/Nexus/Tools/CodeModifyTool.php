<?php

namespace App\Nexus\Tools;

use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class CodeModifyTool extends AbstractNexusTool
{
    private const EXCLUDED = ['.git', 'vendor', 'node_modules', 'storage', 'bootstrap/cache'];
    private const EXTENSIONS = ['php', 'js', 'jsx', 'ts', 'tsx', 'py', 'dart', 'sql', 'html', 'css', 'scss', 'sh', 'ps1'];

    public function name(): string
    {
        return 'nexus.code.modify';
    }

    public function description(): string
    {
        return 'Aplica una modificación explícitamente autorizada a un archivo permitido y devuelve su diff.';
    }

    public function parameters(): array
    {
        return [
            'path' => ['type' => 'string', 'required' => true],
            'expected_sha256' => ['type' => 'string', 'required' => true],
            'new_content' => ['type' => 'string', 'required' => true],
        ];
    }

    public function permissions(): array
    {
        return ['nexus.code.modify'];
    }

    public function requiresConfirmation(): bool
    {
        return true;
    }

    protected function validationRules(): array
    {
        return [
            'path' => ['required', 'string', 'max:255'],
            'expected_sha256' => ['required', 'string', 'size:64'],
            'new_content' => ['required', 'string'],
        ];
    }

    protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
    {
        $path = $this->safePath($parameters['path']);
        if ($path === null || ! File::exists($path) || ! File::isFile($path)) {
            return NexusToolResult::failure('invalid_code_file', 'El archivo no está permitido o no existe.');
        }

        $before = File::get($path);
        $actualHash = hash('sha256', $before);
        if (! hash_equals($parameters['expected_sha256'], $actualHash)) {
            return NexusToolResult::failure(
                'file_changed_since_snapshot',
                'El archivo cambió después del snapshot; no se sobrescribirá silenciosamente.',
                ['expected_sha256' => $parameters['expected_sha256'], 'actual_sha256' => $actualHash]
            );
        }
        if ($before === $parameters['new_content']) {
            return NexusToolResult::failure('no_change', 'La modificación no produce ningún cambio.');
        }

        $diff = $this->diff($parameters['path'], $before, $parameters['new_content']);
        File::put($path, $parameters['new_content']);
        $afterHash = hash('sha256', $parameters['new_content']);

        return NexusToolResult::success([
            'path' => $parameters['path'],
            'diff' => $diff,
            'before_sha256' => $actualHash,
            'after_sha256' => $afterHash,
            'rollback' => [
                'available' => true,
                'path' => $parameters['path'],
                'original_content' => $before,
                'original_sha256' => $actualHash,
                'modified_sha256' => $afterHash,
            ],
            'read_only' => false,
        ], [
            'tool' => $this->name(),
            'modified' => true,
            'diff_generated' => true,
        ]);
    }

    private function safePath(string $relative): ?string
    {
        $relative = trim(str_replace('\\', '/', $relative), '/');
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }
        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        if (! in_array($extension, self::EXTENSIONS, true)
            || collect(self::EXCLUDED)->contains(fn (string $item): bool => $relative === $item || Str::startsWith($relative, $item.'/'))) {
            return null;
        }
        $root = realpath(base_path());
        $candidate = realpath(base_path($relative));
        if ($root === false || $candidate === false) {
            return null;
        }
        return Str::startsWith(str_replace('\\', '/', $candidate).'/', rtrim(str_replace('\\', '/', $root), '/').'/')
            ? $candidate
            : null;
    }

    private function diff(string $path, string $before, string $after): string
    {
        $old = explode("\n", $before);
        $new = explode("\n", $after);
        $lines = ["--- a/{$path}", "+++ b/{$path}"];
        $max = max(count($old), count($new));
        for ($index = 0; $index < $max; $index++) {
            if (($old[$index] ?? null) === ($new[$index] ?? null)) {
                continue;
            }
            if (array_key_exists($index, $old)) {
                $lines[] = '-'.($old[$index] ?? '');
            }
            if (array_key_exists($index, $new)) {
                $lines[] = '+'.($new[$index] ?? '');
            }
        }
        return implode("\n", $lines);
    }
}
