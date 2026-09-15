<?php

namespace App\Services;

final class NexusTestResultParser
{
    /** @return array{tests:int|null,assertions:int|null,failures:int|null,errors:int|null,skipped:int|null,incomplete:int|null,warnings:int|null,deprecations:int|null,failure_details:array<int,array<string,mixed>>} */
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
            'failure_details' => [],
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

        $metrics['failure_details'] = $this->parseFailureDetails($output);

        return $metrics;
    }

    /** @return array<int,array{test:string,class:string|null,method:string|null,message:string,file:string|null,line:int|null,trace:string}> */
    private function parseFailureDetails(string $output): array
    {
        $lines = preg_split('/\R/', $output) ?: [];
        $details = [];
        $current = null;

        foreach ($lines as $line) {
            $cleanLine = preg_replace('/\x1B\[[0-?]*[ -\/]*[@-~]/', '', $line) ?? $line;
            $trimmed = trim($cleanLine);
            if (preg_match('/^\d+\)\s+(.+)$/', $trimmed, $match) === 1) {
                if ($current !== null) {
                    $details[] = $this->finishFailure($current);
                }

                $test = trim($match[1]);
                $class = null;
                $method = null;
                if (preg_match('/^(.+?)(?:::|::)([^:]+)$/', $test, $testMatch) === 1) {
                    $class = $testMatch[1];
                    $method = $testMatch[2];
                }

                $current = [
                    'test' => $test,
                    'class' => $class,
                    'method' => $method,
                    'message_lines' => [],
                    'trace_lines' => [],
                    'file' => null,
                    'line' => null,
                    'in_trace' => false,
                ];
                continue;
            }

            if ($current === null) {
                continue;
            }

            if (preg_match('/^(.*?\.php):(\d+)(?::\d+)?$/', $trimmed, $location) === 1) {
                if ($current['file'] === null) {
                    $current['file'] = $location[1];
                    $current['line'] = (int) $location[2];
                }
                $current['in_trace'] = true;
            }

            if ($current['in_trace']) {
                $current['trace_lines'][] = $cleanLine;
            } elseif ($trimmed !== '') {
                $current['message_lines'][] = $trimmed;
            }
        }

        if ($current !== null) {
            $details[] = $this->finishFailure($current);
        }

        return $details;
    }

    /** @param array<string,mixed> $failure */
    private function finishFailure(array $failure): array
    {
        return [
            'test' => $failure['test'],
            'class' => $failure['class'],
            'method' => $failure['method'],
            'message' => implode(' ', $failure['message_lines']),
            'file' => $failure['file'],
            'line' => $failure['line'],
            'trace' => trim(implode("\n", $failure['trace_lines'])),
        ];
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
