<?php

namespace Tests\Unit;

use App\Services\NexusTestResultParser;
use App\Services\NexusValidationService;
use Tests\TestCase;

class NexusValidationServiceTest extends TestCase
{
    public function test_validation_result_preserves_stdout_stderr_exit_code_and_metrics(): void
    {
        $result = app(NexusValidationService::class)->validate([], 'routes');
        $check = collect($result['checks'])->firstWhere('command', 'php artisan route:list');

        $this->assertIsArray($check);
        $this->assertArrayHasKey('stdout', $check);
        $this->assertArrayHasKey('stderr', $check);
        $this->assertArrayHasKey('exit_code', $check);
        $this->assertArrayHasKey('tests', $check);
        $this->assertArrayHasKey('assertions', $check);
        $this->assertArrayHasKey('failures', $check);
        $this->assertArrayHasKey('errors', $check);
        $this->assertArrayHasKey('skipped', $check);
        $this->assertArrayHasKey('incomplete', $check);
        $this->assertArrayHasKey('warnings', $check);
        $this->assertArrayHasKey('deprecations', $check);
        $this->assertSame('php artisan route:list', $check['command']);
    }
}
