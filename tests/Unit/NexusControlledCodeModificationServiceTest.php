<?php

namespace Tests\Unit;

use App\Contracts\NexusTool;
use App\Models\User;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolRegistry;
use App\Nexus\NexusToolResult;
use App\Services\NexusControlledCodeModificationService;
use Tests\TestCase;

class NexusControlledCodeModificationServiceTest extends TestCase
{
    public function test_rejects_without_confirmation_or_admin(): void
    {
        $service = new NexusControlledCodeModificationService(new NexusToolRegistry());
        $result = $service->execute($this->plan(), new User(['rol' => 'usuario']), 'session', false, []);

        $this->assertSame('rejected', $result['status']);
        $this->assertStringContainsString('administrador', $result['error']['message']);
    }

    public function test_rejects_plan_without_required_modification_parameters(): void
    {
        $service = new NexusControlledCodeModificationService(new NexusToolRegistry());
        $result = $service->execute($this->plan(), new User(['id' => 1, 'rol' => 'admin']), 'session', true, []);

        $this->assertSame('rejected', $result['status']);
        $this->assertStringContainsString('expected_sha256', $result['error']['message']);
    }

    public function test_applies_diff_and_validates_successfully(): void
    {
        $registry = new NexusToolRegistry([
            new ControlledCodeTool('nexus.code.modify', true),
            new ControlledCodeTool('nexus.code.validate', true),
        ]);
        $service = new NexusControlledCodeModificationService($registry);
        $user = new User(['rol' => 'admin']);
        $user->id = 7;

        $result = $service->execute(
            $this->plan(),
            $user,
            'session-success',
            true,
            ['path' => 'tests/example.php', 'expected_sha256' => str_repeat('a', 64), 'new_content' => '<?php'],
        );

        $this->assertSame('completed', $result['status']);
        $this->assertNotEmpty($result['run_id']);
        $this->assertTrue($result['rollback']['available']);
        $this->assertSame('--- a/tests/example.php', $result['diff']);
    }

    public function test_validation_failure_is_not_successful(): void
    {
        $registry = new NexusToolRegistry([
            new ControlledCodeTool('nexus.code.modify', true),
            new ControlledCodeTool('nexus.code.validate', false),
        ]);
        $service = new NexusControlledCodeModificationService($registry);
        $user = new User(['rol' => 'admin']);
        $user->id = 7;

        $result = $service->execute(
            $this->plan(),
            $user,
            'session-failure',
            true,
            ['path' => 'tests/example.php', 'expected_sha256' => str_repeat('a', 64), 'new_content' => '<?php'],
        );

        $this->assertSame('failed', $result['status']);
        $this->assertSame('validation_failed_rolled_back', $result['error']['code']);
    }

    private function plan(): array
    {
        return [
            'plan_id' => 'plan-code',
            'objective' => 'Modificar código controladamente',
            'status' => 'ready_for_authorization',
            'executed' => false,
            'required_tools' => ['nexus.code.modify'],
            'required_permissions' => ['nexus.code.modify'],
        ];
    }

}

final class ControlledCodeTool implements NexusTool
{
    public function __construct(private readonly string $toolName, private readonly bool $successful)
    {
    }

    public function name(): string { return $this->toolName; }
    public function description(): string { return 'Controlled code test tool.'; }
    public function parameters(): array { return []; }
    public function permissions(): array { return []; }
    public function requiresConfirmation(): bool { return false; }
    public function execute(array $parameters, NexusToolContext $context): NexusToolResult
    {
        if (! $this->successful) {
            return NexusToolResult::failure('validation_failed', 'Validation failed.');
        }
        if ($this->toolName === 'nexus.code.validate') {
            return NexusToolResult::success(['passed' => true]);
        }
        return NexusToolResult::success([
            'path' => $parameters['path'],
            'diff' => '--- a/tests/example.php',
            'rollback' => [
                'path' => $parameters['path'],
                'original_content' => '<?php',
                'modified_sha256' => hash('sha256', '<?php'),
            ],
        ]);
    }
}
