<?php

namespace App\Services;

final class NexusRootCauseAnalysisService
{
    /**
     * @param array<string, mixed> $symptom
     * @param array<int, array<string, mixed>> $evidence
     * @return array<string, mixed>
     */
    public function analyze(array $symptom, array $evidence = [], ?int $projectId = null, int $hours = 168): array
    {
        $facts = [];
        $hypotheses = [];
        $missing = [];

        foreach ($evidence as $item) {
            $facts[] = [
                'statement' => $item['statement'] ?? ($item['title'] ?? 'Evento observado'),
                'source' => $item['source'] ?? 'evidence',
                'timestamp' => $item['timestamp'] ?? now()->toIso8601String(),
            ];
        }

        $candidateUpdates = array_values(array_filter($evidence, fn (array $item): bool => ($item['type'] ?? '') === 'update'));
        if ($candidateUpdates !== []) {
            $hypotheses[] = [
                'statement' => 'Un cambio reciente del proyecto coincide con la ventana del problema.',
                'support' => array_values(array_map(fn (array $item): string => $item['title'] ?? ($item['statement'] ?? 'Cambio reciente'), $candidateUpdates)),
                'confidence' => 0.71,
                'status' => 'hypothesis',
            ];
        }

        $candidateIncidents = array_values(array_filter($evidence, fn (array $item): bool => ($item['type'] ?? '') === 'incident'));
        if ($candidateIncidents !== []) {
            $hypotheses[] = [
                'statement' => 'El problema se repite en incidentes que comparten patrón o entorno.',
                'support' => array_values(array_map(fn (array $item): string => $item['title'] ?? ($item['statement'] ?? 'Incidente observado'), $candidateIncidents)),
                'confidence' => 0.67,
                'status' => 'hypothesis',
            ];
        }

        if ($candidateUpdates === [] && $candidateIncidents === []) {
            $missing[] = 'No hay cambios ni incidentes correlacionados en la ventana analizada.';
        }

        $missing[] = 'Falta una validación directa del entorno o del caso de reproducción para confirmar la causa.';

        $probableCause = collect($hypotheses)->sortByDesc('confidence')->first();

        return [
            'project_id' => $projectId,
            'window_hours' => $hours,
            'symptom' => $symptom,
            'facts' => $facts,
            'evidence' => $evidence,
            'hypotheses' => $hypotheses,
            'probable_cause' => $probableCause ?: [
                'statement' => 'No existe causa probable confirmada con evidencias suficientes.',
                'confidence' => 0.0,
                'status' => 'insufficient_evidence',
            ],
            'missing_information' => array_values(array_unique($missing)),
            'status' => 'root_cause_analysis',
            'read_only' => true,
        ];
    }
}
