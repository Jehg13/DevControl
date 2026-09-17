<?php

namespace App\Services;

final class NexusDeveloperIntelligenceOrchestrator
{
    public function __construct(
        private readonly NexusRepositoryIntelligenceService $repository,
        private readonly NexusCodeIntelligenceService $code,
        private readonly NexusCommitIntelligenceService $commits,
        private readonly NexusDependencyIntelligenceService $dependencies,
        private readonly NexusTestIntelligenceService $tests,
        private readonly NexusCodeIssueIntelligenceService $issues,
        private readonly NexusLogIntelligenceService $logs,
        private readonly NexusSecurityIntelligenceService $security,
        private readonly NexusDeploymentIntelligenceService $deployments,
    ) {
    }

    public function analyze(?int $projectId = null, ?string $relativePath = null): array
    {
        $repo = $this->repository->analyze($relativePath);
        $code = $this->code->analyze($projectId, $relativePath, false, 150);
        $commit = $this->commits->recent($projectId, 10);
        $deps = $this->dependencies->analyze($relativePath);
        $tests = $this->tests->analyze($relativePath);
        $issues = $this->issues->analyze($relativePath);
        $logs = $this->logs->analyze($relativePath);
        $security = $this->security->analyze($relativePath);
        $deployment = $this->deployments->analyze($projectId, 168);

        $conclusions = [
            'repository' => ['summary' => 'Se detectó la estructura del repositorio y dependencias principales.', 'evidence' => $repo['evidence']],
            'code' => ['summary' => 'Se analizaron archivos y se registraron símbolos y referencias.', 'evidence' => array_slice($code['files'] ?? [], 0, 10)],
            'commits' => ['summary' => 'Se revisaron los cambios recientes del repositorio.', 'evidence' => $commit['commits']],
            'dependencies' => ['summary' => 'Se identificaron dependencias relevantes del proyecto.', 'evidence' => $deps['problematic_dependencies']],
            'tests' => ['summary' => 'Se detectaron pruebas del proyecto.', 'evidence' => $tests['tests']],
            'issues' => ['summary' => 'Se evaluaron patrones sospechosos en código.', 'evidence' => $issues['issues']],
            'logs' => ['summary' => 'Se revisaron entradas de log con mensajes de error.', 'evidence' => $logs['log_files']],
            'security' => ['summary' => 'Se buscaron señales de secretos o riesgos de ejecución.', 'evidence' => $security['findings']],
            'deployment' => ['summary' => 'Se correlacionaron eventos recientes con despliegues e incidentes.', 'evidence' => $deployment['correlations']],
        ];

        return [
            'project_id' => $projectId,
            'root' => $relativePath ?: '.',
            'repository' => $repo,
            'code' => $code,
            'commits' => $commit,
            'dependencies' => $deps,
            'tests' => $tests,
            'issues' => $issues,
            'logs' => $logs,
            'security' => $security,
            'deployment' => $deployment,
            'conclusions' => $conclusions,
            'read_only' => true,
        ];
    }
}
