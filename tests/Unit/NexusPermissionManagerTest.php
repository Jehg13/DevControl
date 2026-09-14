<?php

namespace Tests\Unit;

use App\Models\NexusPermissionAudit;
use App\Models\User;
use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolResult;
use App\Services\NexusPermissionManager;
use App\Exceptions\NexusToolPermissionException;
use Tests\TestCase;

class NexusPermissionManagerTest extends TestCase
{
    public function test_it_denies_tools_without_explicit_permissions(): void
    {
        $tool = new class extends AbstractNexusTool
        {
            public function name(): string { return 'tests.undeclared'; }
            public function description(): string { return 'Sin permiso'; }
            public function parameters(): array { return []; }
            protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
            {
                return NexusToolResult::success();
            }
        };

        try {
            $tool->execute([], new NexusToolContext(new User(['rol' => 'admin'])));
            $this->fail('Expected permission exception.');
        } catch (NexusToolPermissionException $exception) {
            $this->assertSame('permission_denied', $exception->errorCode);
        }
        $this->assertSame('permission_not_declared', NexusPermissionAudit::query()->latest('id')->value('status'));
    }

    public function test_it_requires_confirmation_for_sensitive_operations(): void
    {
        $tool = new class extends AbstractNexusTool
        {
            public function name(): string { return 'tests.file.delete'; }
            public function description(): string { return 'Elimina'; }
            public function parameters(): array { return []; }
            public function permissions(): array { return ['nexus.write', 'destructive_action']; }
            protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
            {
                return NexusToolResult::success(['deleted' => true]);
            }
        };

        $pending = $tool->execute([], new NexusToolContext(new User(['rol' => 'admin'])));
        $this->assertFalse($pending->successful);
        $this->assertSame('confirmation_required', $pending->errorCode);
        $this->assertSame('approval_required', NexusPermissionAudit::query()->latest('id')->value('status'));

        $confirmed = $tool->execute([], new NexusToolContext(new User(['rol' => 'admin']), confirmed: true));
        $this->assertTrue($confirmed->successful);
        $this->assertSame('succeeded', NexusPermissionAudit::query()->latest('id')->value('status'));
        $this->assertSame('high', NexusPermissionAudit::query()->latest('id')->value('risk_level'));
    }

    public function test_it_allows_explicitly_granted_permissions_and_never_changes_policy(): void
    {
        $manager = app(NexusPermissionManager::class);
        $auditId = $manager->authorize(
            'tests.read',
            ['read_project'],
            [],
            new NexusToolContext(grantedPermissions: ['read_project']),
            false
        );

        $this->assertGreaterThan(0, $auditId);
        $this->assertSame('granted', NexusPermissionAudit::find($auditId)->status);
        $this->assertSame('safe', $manager->policy()['mode']);
        $this->assertSame([], config('nexus.security.autonomous_tools'));
    }
}
