<?php

namespace Tests\Unit;

use App\Contracts\NexusTool;
use App\Models\NexusRun;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolRegistry;
use App\Nexus\NexusToolResult;
use App\Services\NexusControlledExecutionService;
use Tests\TestCase;

class NexusControlledExecutionServiceTest extends TestCase
{
    public function test_rejects_invalid_plan_before_creating_execution(): void
    {
        $service = new NexusControlledExecutionService(new NexusToolRegistry());

        $result = $service->execute([], null, 'session-1');

        $this->assertSame('rejected', $result['status']);
        $this->assertSame('invalid_plan', $result['error']['code']);
        $this->assertSame([], $result['executed']);
    }

    public function test_executes_steps_sequentially_and_records_evidence(): void
    {
        $registry = new NexusToolRegistry([
            new ControlledExecutionFakeTool('tests.controlled.success'),
        ]);
        $service = new NexusControlledExecutionService($registry);

        $result = $service->execute($this->plan('tests.controlled.success'), null, 'session-1');

        $this->assertSame('completed', $result['status']);
        $this->assertCount(1, $result['executed']);
        $this->assertSame('session-1', $result['executed'][0]['session_id']);
        $this->assertNull($result['executed'][0]['user_id']);
        $this->assertSame('tests.controlled.success', $result['executed'][0]['tool']);
        $this->assertNotNull($result['executed'][0]['evidence']);
        $this->assertSame('completed', NexusRun::find($result['run_id'])->status);
    }

    public function test_stops_after_tool_error(): void
    {
        $registry = new NexusToolRegistry([
            new ControlledExecutionFakeTool('tests.controlled.failure', false),
            new ControlledExecutionFakeTool('tests.controlled.success'),
        ]);
        $service = new NexusControlledExecutionService($registry);
        $plan = $this->plan('tests.controlled.failure');
        $plan['steps'][0]['tools'] = ['tests.controlled.failure', 'tests.controlled.success'];

        $result = $service->execute($plan, null, 'session-1');

        $this->assertSame('failed', $result['status']);
        $this->assertCount(1, $result['executed']);
        $this->assertSame('tool_failed', $result['failure']['code']);
    }

    public function test_confirmation_is_enforced_by_laravel_tool(): void
    {
        $registry = new NexusToolRegistry([new ControlledExecutionFakeTool('tests.controlled.confirm', true, true)]);
        $service = new NexusControlledExecutionService($registry);

        $result = $service->execute($this->plan('tests.controlled.confirm'), null, 'session-1');

        $this->assertSame('failed', $result['status']);
        $this->assertSame('confirmation_required', $result['failure']['code']);
    }

    public function test_timeout_stops_before_next_step(): void
    {
        $registry = new NexusToolRegistry([new ControlledExecutionFakeTool('tests.controlled.success')]);
        $service = new NexusControlledExecutionService($registry);
        $plan = $this->plan('tests.controlled.success');
        $plan['steps'][] = $plan['steps'][0] + ['step_id' => 'second', 'depends_on' => ['first']];

        $result = $service->execute($plan, null, 'session-1', false, [], -1);

        $this->assertSame('failed', $result['status']);
        $this->assertSame('execution_timeout', $result['failure']['code']);
    }

    public function test_rejects_plan_with_unregistered_tool(): void
    {
        $service = new NexusControlledExecutionService(new NexusToolRegistry());
        $result = $service->execute($this->plan('tests.missing'), null, 'session-1');

        $this->assertSame('rejected', $result['status']);
        $this->assertSame('invalid_plan', $result['error']['code']);
    }

    public function test_rejects_sensitive_plan_without_authorized_user(): void
    {
        $registry = new NexusToolRegistry([
            new ControlledExecutionFakeTool('tests.controlled.success'),
        ]);
        $service = new NexusControlledExecutionService($registry);
        $plan = $this->plan('tests.controlled.success');
        $plan['required_permissions'] = ['nexus.code.modify'];

        $result = $service->execute($plan, null, 'session-1');

        $this->assertSame('rejected', $result['status']);
        $this->assertSame('permission_denied', $result['error']['code']);
    }

    private function plan(string $tool): array
    {
        return [
            'plan_id' => 'plan-test',
            'objective' => 'Controlled test',
            'status' => 'ready_for_authorization',
            'executed' => false,
            'required_tools' => [$tool],
            'required_permissions' => [],
            'steps' => [[
                'step_id' => 'first',
                'depends_on' => [],
                'tools' => [$tool],
            ]],
        ];
    }
}

final class ControlledExecutionFakeTool implements NexusTool
{
    public function __construct(
        private readonly string $toolName,
        private readonly bool $successful = true,
        private readonly bool $confirmation = false,
    ) {
    }

    public function name(): string { return $this->toolName; }
    public function description(): string { return 'Controlled execution test tool.'; }
    public function parameters(): array { return []; }
    public function permissions(): array { return []; }
    public function requiresConfirmation(): bool { return $this->confirmation; }
    public function execute(array $parameters, NexusToolContext $context): NexusToolResult
    {
        return $this->successful
            ? NexusToolResult::success(['verified' => true])
            : NexusToolResult::failure('tool_failed', 'Controlled tool failed.');
    }
}
