<?php

namespace App\Services;

use App\Models\Proyecto;

final class NexusDeveloperIntelligenceService
{
    public function __construct(
        private readonly NexusGitIntelligenceService $git,
        private readonly NexusDeploymentIntelligenceService $deployments,
        private readonly NexusMonitoringIntelligenceService $monitoring,
        private readonly NexusRootCauseAnalysisService $rootCause,
    ) {
    }

    public function analyzeProject(?int $projectId = null, int $hours = 168): array
    {
        $project = $projectId !== null ? Proyecto::find($projectId) : null;

        $deployment = $this->deployments->analyze($projectId, $hours);
        $monitoring = $this->monitoring->detect($projectId, $hours);
        $git = $this->git->recentHistory($projectId, 20);

        $symptom = [
            'title' => $project?->nombre ?? 'Estado del proyecto',
            'description' => 'Se analizan cambios recientes, incidentes y anomalías observadas para identificar correlaciones verificables.',
        ];

        $evidence = [];
        foreach ($deployment['updates'] ?? [] as $update) {
            $evidence[] = [
                'type' => 'update',
                'title' => $update['title'],
                'statement' => 'Cambio registrado con commit '.$update['commit'],
                'source' => 'actualizaciones',
                'timestamp' => (string) $update['date'],
            ];
        }
        foreach ($deployment['incidents'] ?? [] as $incident) {
            $evidence[] = [
                'type' => 'incident',
                'title' => $incident['title'],
                'statement' => 'Incidente confirmado con prioridad '.$incident['priority'],
                'source' => 'incidentes',
                'timestamp' => (string) $incident['date'],
            ];
        }

        $rootCause = $this->rootCause->analyze($symptom, $evidence, $projectId, $hours);

        return [
            'project' => $project ? [
                'id' => $project->id,
                'name' => $project->nombre,
            ] : null,
            'git' => $git,
            'deployment' => $deployment,
            'monitoring' => $monitoring,
            'root_cause' => $rootCause,
            'summary' => [
                'alert_count' => count($monitoring['alerts'] ?? []),
                'correlation_count' => count($deployment['correlations'] ?? []),
                'commit_count' => $git['commit_count'] ?? 0,
            ],
            'read_only' => true,
        ];
    }
}
