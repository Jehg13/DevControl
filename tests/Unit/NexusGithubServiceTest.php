<?php

namespace Tests\Unit;

use App\Exceptions\NexusGithubException;
use App\Services\NexusGithubService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NexusGithubServiceTest extends TestCase
{
    public function test_it_reads_repository_and_branches_without_requiring_a_token(): void
    {
        config(['services.github.token' => null]);
        Http::fake([
            'https://api.github.com/repos/acme/demo' => Http::response([
                'name' => 'demo',
                'default_branch' => 'main',
            ]),
            'https://api.github.com/repos/acme/demo/branches*' => Http::response([
                ['name' => 'main'],
            ]),
        ]);

        $service = app(NexusGithubService::class);

        $this->assertSame('demo', $service->repository(null, 'acme', 'demo')['name']);
        $this->assertSame('main', $service->branches(null, 'acme', 'demo')[0]['name']);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/acme/demo'
            && $request->header('Authorization') === []);
    }

    public function test_it_reports_rate_limits_and_missing_repositories(): void
    {
        Http::fake([
            'https://api.github.com/repos/acme/missing' => Http::response(
                ['message' => 'Not Found'],
                404
            ),
            'https://api.github.com/repos/acme/limited' => Http::response(
                ['message' => 'API rate limit exceeded'],
                403,
                ['X-RateLimit-Remaining' => '0']
            ),
        ]);

        try {
            app(NexusGithubService::class)->repository(null, 'acme', 'missing');
            $this->fail('Expected missing repository exception.');
        } catch (NexusGithubException $exception) {
            $this->assertSame('github_not_found', $exception->errorCode);
        }

        try {
            app(NexusGithubService::class)->repository(null, 'acme', 'limited');
            $this->fail('Expected rate limit exception.');
        } catch (NexusGithubException $exception) {
            $this->assertSame('github_rate_limited', $exception->errorCode);
        }
    }

    public function test_file_reads_are_isolated_by_path_and_do_not_reuse_previous_content(): void
    {
        Http::fake([
            'https://api.github.com/repos/acme/demo/contents/composer.json*' => Http::response([
                'path' => 'composer.json',
                'content' => base64_encode('{"require":{"laravel/framework":"^10.10"}}'),
            ]),
            'https://api.github.com/repos/acme/demo/contents/prueba-inexistente-nexus.json*' => Http::response([
                'message' => 'Not Found',
            ], 404),
        ]);

        $service = app(NexusGithubService::class);
        $composer = $service->file(null, 'composer.json', 'acme', 'demo');

        $this->assertSame(
            '{"require":{"laravel/framework":"^10.10"}}',
            $composer['decoded_content']
        );

        try {
            $service->file(null, 'prueba-inexistente-nexus.json', 'acme', 'demo');
            $this->fail('Expected missing file exception.');
        } catch (NexusGithubException $exception) {
            $this->assertSame('github_not_found', $exception->errorCode);
        }

        $composerAgain = $service->file(null, 'composer.json', 'acme', 'demo');
        $this->assertSame($composer['decoded_content'], $composerAgain['decoded_content']);
        Http::assertSentCount(3);
    }
}
