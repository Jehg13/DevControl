<?php

namespace App\Services;

final class NexusTestResultParser
{
    /** @return array{tests:int|null,assertions:int|null,failures:int|null,errors:int|null,skipped:int|null,incomplete:int|null,warnings:int|null,deprecations:int|null} */
    public function parse(string $output): array
    {
        $metrics = [
            'tests' => null,
            'assertions' => null,
            'failures' => null,
            'errors' => null,
            'skipped' => null,
            'incomplete' => null,
            'warnings' => null,
            'deprecations' => null,
        ];

        $summary = $this->lineContaining($output, 'Tests:');
        if ($summary !== null) {
            foreach (array_keys($metrics) as $key) {
                $metrics[$key] = 0;
            }
            $this->parseCount($summary, 'assertions', $metrics);
            $this->parseCount($summary, 'failed', $metrics, 'failures');
            $this->parseCount($summary, 'failure', $metrics, 'failures');
            $this->parseCount($summary, 'errors?', $metrics, 'errors');
            $this->parseCount($summary, 'skipped', $metrics);
            $this->parseCount($summary, 'incomplete', $metrics);
            $this->parseCount($summary, 'warnings?', $metrics, 'warnings');
            $this->parseCount($summary, 'deprecated', $metrics, 'deprecations');

            $parts = [];
            foreach (['passed', 'failed', 'skipped', 'incomplete', 'warning', 'error'] as $label) {
                if (preg_match('/(\d+)\s+'.preg_quote($label, '/').'/i', $summary, $match) === 1) {
                    if (in_array($label, ['warning', 'error'], true)) {
                        continue;
                    }
                    $parts[] = (int) $match[1];
                }
            }
            $metrics['tests'] = array_sum($parts);
        }

        foreach ([
            'failures' => 'Failures',
            'errors' => 'Errors',
            'skipped' => 'Skipped',
            'incomplete' => 'Incomplete',
            'warnings' => 'Warnings',
            'deprecations' => 'Deprecations',
        ] as $key => $label) {
            if (preg_match('/\b'.$label.':\s*(\d+)/i', $output, $match) === 1) {
                $metrics[$key] = (int) $match[1];
            }
        }

        if ($metrics['tests'] === null && preg_match('/\bTests:\s*(\d+)/i', $output, $match) === 1) {
            $metrics['tests'] = (int) $match[1];
        }

        if (preg_match('/\bTests:\s*(\d+)\s*(?:tests?)?\s*,\s*Assertions:\s*(\d+)/i', $output, $match) === 1) {
            $metrics['tests'] = (int) $match[1];
            $metrics['assertions'] = (int) $match[2];
        }

        return $metrics;
    }

    private function lineContaining(string $output, string $needle): ?string
    {
        foreach (preg_split('/\R/', $output) ?: [] as $line) {
            if (str_contains($line, $needle)) {
                return trim($line);
            }
        }

        return null;
    }

    private function parseCount(string $summary, string $label, array &$metrics, ?string $key = null): void
    {
        if (preg_match('/(\d+)\s+'.$label.'/i', $summary, $match) === 1) {
            $metrics[$key ?? $label] = (int) $match[1];
        }
    }
}
