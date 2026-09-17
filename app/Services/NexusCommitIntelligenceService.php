<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

final class NexusCommitIntelligenceService
{
    public function recent(?int $projectId = null, int $limit = 20): array
    {
        $log = $this->git(['log', '--pretty=format:%H%x1f%an%x1f%ad%x1f%s', '--date=iso-strict', '-n', (string) max(1, $limit)]);
        $commits = [];

        foreach (preg_split('/\r\n|\n|\r/', $log) ?: [] as $entry) {
            $parts = explode("\x1f", trim($entry));
            if (count($parts) < 4) {
                continue;
            }
            $commits[] = [
                'sha' => $parts[0],
                'author' => $parts[1],
                'date' => $parts[2],
                'subject' => $parts[3],
            ];
        }

        return [
            'project_id' => $projectId,
            'commit_count' => count($commits),
            'commits' => $commits,
            'read_only' => true,
        ];
    }

    public function changedFiles(string $sha): array
    {
        $raw = $this->git(['show', '--pretty=format:', '--name-only', $sha]);
        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\n|\r/', $raw) ?: [])));
    }

    public function diff(string $sha): array
    {
        $raw = $this->git(['show', '--stat', '--summary', '--format=%H%n%an%n%ad%n%s', '--date=iso-strict', $sha]);
        return ['sha' => $sha, 'raw' => trim($raw), 'files' => $this->changedFiles($sha)];
    }

    private function git(array $command): string
    {
        $repoRoot = base_path();
        if (! File::exists($repoRoot.'/.git')) {
            return '';
        }

        $process = new Process(['git', '-C', $repoRoot, ...$command]);
        $process->run();
        if (! $process->isSuccessful()) {
            return '';
        }

        return trim($process->getOutput());
    }
}
