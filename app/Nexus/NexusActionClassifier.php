<?php

namespace App\Nexus;

use Illuminate\Support\Str;

final class NexusActionClassifier
{
    public function classify(NexusRuntimeRequest $request): ?array
    {
        $text = trim(Str::lower(Str::ascii($request->message)));
        $text = ltrim($text, "¿¡ \t\n\r\0\x0B");
        $projectId = $request->projectId ?? ($request->context['project_id'] ?? null);

        if ($this->hasAny($text, ['ignora los permisos', 'ignora permisos', 'concedete permisos', 'concedete permiso', 'modifica directamente', 'saltate la seguridad', 'salta los permisos'])) {
            return $this->action('security_violation', 'security_boundary', [], [], false, false);
        }

        if ($this->hasAny($text, ['herramienta que no tienes autorizada', 'herramienta no autorizada', 'ejecuta una herramienta que no tienes'])) {
            return $this->action('unauthorized_tool', 'tool_registry', [], [], false, false);
        }

        if ($this->hasAny($text, ['git status', 'estado de git', 'cambios locales', 'que cambios locales tiene'])
            && ! $this->hasAny($text, ['commit', 'guarda los cambios en github', 'guardar los cambios en github'])) {
            return $this->action('git_status', 'repository', ['project_id' => $projectId], ['nexus.read'], false);
        }

        if ($this->isQuestion($text)) {
            return null;
        }

        if ($this->isTestExecutionInvestigation($text)) {
            return null;
        }

        if ($this->hasAny($text, ['diff de git', 'git diff', 'diferencias de git', 'diferencias del repositorio'])) {
            return $this->action('git_diff', 'repository', [], ['nexus.read'], false, false);
        }

        if ($this->hasAny($text, ['push', 'sube la rama', 'subir la rama'])) {
            return null;
        }

        if ($this->hasAny($text, ['commit', 'guarda los cambios en github', 'guardar los cambios en github'])) {
            return $this->action(
                'git_commit',
                'repository',
                array_filter(['project_id' => $projectId, 'message' => $this->commitMessage($request->message)], static fn ($value) => $value !== null),
                ['nexus.write', 'github.write'],
                true
            );
        }

        if ($this->hasAny($text, [
            'ejecuta las pruebas',
            'ejecuta los tests',
            'quiero ejecutar las pruebas',
            'quiero ejecutar los tests',
            'quiero que corras los tests',
            'quiero que corras las pruebas',
            'corre phpunit',
            'corre los tests',
            'corre las pruebas',
            'valida la implementacion',
            'valida la implementación',
        ])) {
            $suite = str_contains($text, 'phpunit') ? 'all' : (str_contains($text, 'nexus') ? 'nexus' : 'all');

            return $this->action('test_execution', 'repository', ['suite' => $suite], ['nexus.read'], false);
        }

        if ($this->hasAny($text, ['cambia el texto', 'cambia la vista', 'edita este archivo', 'modifica app/', 'modifica el archivo', 'archivo de la vista', 'reemplaza este texto', 'editar el archivo'])) {
            $path = $this->extractPath($request->message);

            return $this->action(
                'code_edit_github',
                $path ?? 'repository_file',
                array_filter([
                    'project_id' => $projectId,
                    'path' => $path,
                    'content' => $this->extractQuotedReplacement($request->message),
                ], static fn ($value) => $value !== null),
                ['nexus.write', 'github.write'],
                true
            );
        }

        return null;
    }

    private function action(
        string $action,
        string $target,
        array $arguments,
        array $permissions,
        bool $requiresConfirmation,
        bool $available = true,
    ): array {
        return [
            'action' => $action,
            'target' => $target,
            'arguments' => $arguments,
            'requires_confirmation' => $requiresConfirmation,
            'requested_permissions' => $permissions,
            'source' => 'nexus_action_classifier',
            'available' => $available,
        ];
    }

    private function isQuestion(string $text): bool
    {
        return str_starts_with($text, 'como ')
            || str_starts_with($text, 'que ')
            || str_starts_with($text, 'por que ')
            || str_starts_with($text, 'porque ')
            || str_contains($text, 'como funciona')
            || str_contains($text, 'como funcionan');
    }

    private function isTestExecutionInvestigation(string $text): bool
    {
        return $this->hasAny($text, [
            'investiga por que',
            'investiga porque',
            'por que nexus no detecta',
            'porque nexus no detecta',
            'no detecta que quiero ejecutar',
            'no reconoce que quiero ejecutar',
            'no reconoce mis solicitudes para ejecutar',
            'no reconoce que quiero correr',
            'no detecta correctamente las solicitudes para ejecutar',
        ]);
    }

    private function commitMessage(string $message): string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $message) ?? '');
        return $normalized !== '' ? $normalized : 'Cambios actuales';
    }

    private function extractPath(string $message): ?string
    {
        if (preg_match('/\b((?:app|resources|routes|config|database)\/[A-Za-z0-9._\/-]+\.[A-Za-z0-9]+)\b/i', $message, $matches) === 1) {
            return str_replace('\\', '/', $matches[1]);
        }

        return null;
    }

    private function extractQuotedReplacement(string $message): ?string
    {
        if (preg_match('/de\s+[\'"](?<old>[^\'"]+)[\'"]\s+a\s+[\'"](?<new>[^\'"]+)[\'"]/iu', $message, $matches) === 1) {
            return $matches['new'];
        }

        return null;
    }

    private function hasAny(string $text, array $terms): bool
    {
        foreach ($terms as $term) {
            if (str_contains($text, Str::lower(Str::ascii($term)))) {
                return true;
            }
        }

        return false;
    }
}
