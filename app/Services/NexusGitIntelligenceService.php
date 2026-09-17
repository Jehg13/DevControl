<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

final class NexusGitIntelligenceService
{
    public function recentHistory(?int $projectId = null, int $limit = 20): array
    {
        $branch = $this->git(['rev-parse', '--abbrev-ref', 'HEAD']);
        $log = $this->git([
            'log',
            '--pretty=format:%H%x1f%an%x1f%ad%x1f%s',
            '--date=iso-strict',
            '-n',
            (string) max(1, $limit),
        ]);

        $commits = array_values(array_filter(array_map(function (string $entry): ?array {
            $parts = explode("\x1f", trim($entry));
            if (count($parts) < 4) {
                return null;
            }

            return [
                'sha' => $parts[0],
                'author' => $parts[1],
                'date' => $parts[2],
                'subject' => $parts[3],
            ];
        }, preg_split('/\r\n|\n|\r/', $log) ?: [])));

        return [
            'project_id' => $projectId,
            'branch' => $branch,
            'commit_count' => count($commits),
            'commits' => $commits,
            'read_only' => true,
        ];
    }

    public function commitFiles(string $sha): array
    {
        $raw = $this->git([
            'show',
            '--pretty=format:',
            '--name-only',
            $sha,
        ]);

        return array_values(array_filter(array_map('trim', preg_split('/\r\n|\n|\r/', $raw) ?: [])));
    }

    public function commitDiff(string $sha): array
    {
        $raw = $this->git([
            'show',
            '--stat',
            '--summary',
            '--format=%H%n%an%n%ad%n%s',
            '--date=iso-strict',
            $sha,
        ]);

        return [
            'sha' => $sha,
            'raw' => trim($raw),
            'files' => $this->commitFiles($sha),
        ];
    }

    public function repositoryStatus(): array
    {
        $branch = $this->git(['rev-parse', '--abbrev-ref', 'HEAD']);
        $status = $this->git(['status', '--short']);

        return [
            'branch' => $branch,
            'status_clean' => trim($status) === '',
            'status' => trim($status),
            'read_only' => true,
        ];
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
