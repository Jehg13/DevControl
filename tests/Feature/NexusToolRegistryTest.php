<?php

namespace Tests\Feature;

use App\Nexus\AbstractNexusTool;
use App\Nexus\NexusToolContext;
use App\Nexus\NexusToolRegistry;
use App\Nexus\NexusToolResult;
use App\Models\User;
use Tests\TestCase;

class NexusToolRegistryTest extends TestCase
{
    public function test_tool_definitions_are_structured(): void
    {
        $registry = new NexusToolRegistry([$this->tool()]);

        $definitions = $registry->definitions();

        $this->assertSame('tests.echo', $definitions[0]['name']);
        $this->assertSame(['value' => ['type' => 'string', 'required' => true]], $definitions[0]['parameters']);
        $this->assertSame(['tests.execute'], $definitions[0]['permissions']);
    }

    public function test_registry_rejects_duplicate_tool_names(): void
    {
        $this->expectException(\App\Exceptions\NexusToolException::class);
        $this->expectExceptionMessage('ya está registrada');

        new NexusToolRegistry([$this->tool(), $this->tool()]);
    }

    public function test_tool_rejects_invalid_parameters(): void
    {
        $result = (new NexusToolRegistry([$this->tool()]))->execute(
            'tests.echo',
            [],
            new NexusToolContext(new User(['rol' => 'admin']))
        );

        $this->assertFalse($result->successful);
        $this->assertSame('validation_failed', $result->errorCode);
    }

    public function test_tool_requires_confirmation_and_permission(): void
    {
        $registry = new NexusToolRegistry([$this->tool()]);

        $denied = $registry->execute(
            'tests.echo',
            ['value' => 'hello'],
            new NexusToolContext(new User(['rol' => 'usuario']))
        );
        $this->assertSame('permission_denied', $denied->errorCode);

        $pending = $registry->execute(
            'tests.echo',
            ['value' => 'hello'],
            new NexusToolContext(new User(['rol' => 'admin']))
        );
        $this->assertSame('confirmation_required', $pending->errorCode);
    }

    public function test_confirmed_tool_returns_structured_result(): void
    {
        $result = (new NexusToolRegistry([$this->tool()]))->execute(
            'tests.echo',
            ['value' => 'hello'],
            new NexusToolContext(new User(['rol' => 'admin']), confirmed: true)
        );

        $this->assertTrue($result->successful);
        $this->assertSame(['value' => 'hello'], $result->data);
        $this->assertSame(['ok' => true, 'data' => ['value' => 'hello'], 'error' => null, 'meta' => []], $result->toArray());
    }

    public function test_code_analysis_tool_is_registered_as_read_only(): void
    {
        $definition = collect(app(NexusToolRegistry::class)->definitions())
            ->firstWhere('name', 'nexus.code.analyze');

        $this->assertNotNull($definition);
        $this->assertSame(['nexus.read'], $definition['permissions']);
        $this->assertFalse($definition['requires_confirmation']);
        $this->assertStringContainsString('tecnologías', $definition['description']);
    }

    public function test_code_validation_tool_is_registered_as_read_only(): void
    {
        $definition = collect(app(NexusToolRegistry::class)->definitions())
            ->firstWhere('name', 'nexus.code.validate');

        $this->assertNotNull($definition);
        $this->assertSame(['nexus.read'], $definition['permissions']);
        $this->assertFalse($definition['requires_confirmation']);
    }

    public function test_action_tools_are_registered_with_their_real_permissions(): void
    {
        $status = collect(app(NexusToolRegistry::class)->definitions())
            ->firstWhere('name', 'nexus.git.status');
        $commit = collect(app(NexusToolRegistry::class)->definitions())
            ->firstWhere('name', 'nexus.github.local.commit');

        $this->assertSame(['nexus.read'], $status['permissions']);
        $this->assertFalse($status['requires_confirmation']);
        $this->assertSame(['nexus.write', 'github.write'], $commit['permissions']);
        $this->assertTrue($commit['requires_confirmation']);
    }

    public function test_analysis_save_tool_requires_write_permission_and_confirmation(): void
    {
        $definition = collect(app(NexusToolRegistry::class)->definitions())
            ->firstWhere('name', 'nexus.analysis.save');

        $this->assertNotNull($definition);
        $this->assertSame(['nexus.write'], $definition['permissions']);
        $this->assertTrue($definition['requires_confirmation']);
    }

    public function test_project_understanding_tools_are_registered_as_read_only(): void
    {
        foreach (['nexus.project.understand', 'nexus.project.query'] as $name) {
            $definition = collect(app(NexusToolRegistry::class)->definitions())
                ->firstWhere('name', $name);

            $this->assertNotNull($definition);
            $this->assertSame(['nexus.read'], $definition['permissions']);
            $this->assertFalse($definition['requires_confirmation']);
        }
    }

    public function test_planner_tool_is_registered_without_project_mutation_confirmation(): void
    {
        $definition = collect(app(NexusToolRegistry::class)->definitions())
            ->firstWhere('name', 'nexus.plan.create');

        $this->assertNotNull($definition);
        $this->assertSame(['nexus.read'], $definition['permissions']);
        $this->assertFalse($definition['requires_confirmation']);
    }

    public function test_github_tools_expose_read_and_protected_write_operations(): void
    {
        $inspect = collect(app(NexusToolRegistry::class)->definitions())
            ->firstWhere('name', 'nexus.github.inspect');
        $write = collect(app(NexusToolRegistry::class)->definitions())
            ->firstWhere('name', 'nexus.github.file.write');

        $this->assertSame(['nexus.read'], $inspect['permissions']);
        $this->assertFalse($inspect['requires_confirmation']);
        $this->assertSame(['nexus.write', 'github.write'], $write['permissions']);
        $this->assertTrue($write['requires_confirmation']);

        $mutation = collect(app(NexusToolRegistry::class)->definitions())
            ->firstWhere('name', 'nexus.github.write');
        $this->assertSame(['nexus.write', 'github.write'], $mutation['permissions']);
        $this->assertTrue($mutation['requires_confirmation']);
        $this->assertSame('medium', $write['risk_level']);
        $this->assertSame('medium', $mutation['risk_level']);
    }

    private function tool(): AbstractNexusTool
    {
        return new class extends AbstractNexusTool
        {
            public function name(): string
            {
                return 'tests.echo';
            }

            public function description(): string
            {
                return 'Herramienta de prueba.';
            }

            public function parameters(): array
            {
                return ['value' => ['type' => 'string', 'required' => true]];
            }

            public function permissions(): array
            {
                return ['tests.execute'];
            }

            public function requiresConfirmation(): bool
            {
                return true;
            }

            protected function validationRules(): array
            {
                return ['value' => ['required', 'string']];
            }

            protected function handle(array $parameters, NexusToolContext $context): NexusToolResult
            {
                return NexusToolResult::success(['value' => $parameters['value']]);
            }
        };
    }
}
