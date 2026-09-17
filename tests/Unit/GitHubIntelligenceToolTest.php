<?php

namespace Tests\Unit;

use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolRegistry;
use App\Nexus\Tools\GitHubIntelligenceTool;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GitHubIntelligenceToolTest extends TestCase
{
    public function test_reads_repository_metadata_without_enabling_write_operations(): void
    {
        Http::fake([
            'https://api.github.com/repos/acme/app' => Http::response([
                'full_name' => 'acme/app',
                'default_branch' => 'main',
                'private' => false,
            ]),
        ]);

        $result = (new GitHubIntelligenceTool())->execute([
            'operation' => 'repository',
            'owner' => 'acme',
            'repo' => 'app',
        ], new NexusToolContext(system: true));

        $this->assertTrue($result->successful);
        $this->assertTrue($result->data['read_only']);
        $this->assertSame('acme/app', $result->data['repository']);
        Http::assertSent(fn ($request) => $request->method() === 'GET');
    }

    public function test_correlates_commits_with_devcontrol_records_for_a_project(): void
    {
        Http::fake([
            'https://api.github.com/repos/acme/app/commits*' => Http::response([[
                'sha' => 'abc1234',
                'commit' => ['message' => 'Fix BUG-42'],
            ]]),
        ]);

        $result = (new GitHubIntelligenceTool())->execute([
            'operation' => 'commits',
            'owner' => 'acme',
            'repo' => 'app',
        ], new NexusToolContext(system: true));

        $this->assertTrue($result->successful);
        $this->assertSame('abc1234', $result->data['data'][0]['sha']);
    }

    public function test_rejects_destructive_operations_at_the_tool_boundary(): void
    {
        $result = (new NexusToolRegistry([new GitHubIntelligenceTool()]))->execute(
            'github.intelligence.read',
            ['operation' => 'push', 'owner' => 'acme', 'repo' => 'app'],
            new NexusToolContext(system: true),
        );

        $this->assertFalse($result->successful);
        $this->assertSame('validation_failed', $result->errorCode);
    }
}
