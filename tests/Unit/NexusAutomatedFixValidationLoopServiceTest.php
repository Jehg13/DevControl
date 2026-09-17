<?php

namespace Tests\Unit;

use App\Models\User;
use App\Nexus\NexusToolRegistry;
use App\Services\NexusAutomatedFixValidationLoopService;
use App\Services\NexusControlledCodeModificationService;
use Tests\TestCase;

class NexusAutomatedFixValidationLoopServiceTest extends TestCase
{
    public function test_rejects_an_unbounded_iteration_limit(): void
    {
        $service = $this->service();
        $user = new User(['rol' => 'admin']);
        $user->id = 1;

        $result = $service->execute($this->loop(6), $user, 'session', true);

        $this->assertSame('rejected', $result['status']);
        $this->assertStringContainsString('max_iterations', $result['error']['message']);
    }

    public function test_rejects_a_loop_without_explicit_confirmation(): void
    {
        $service = $this->service();
        $user = new User(['rol' => 'admin']);
        $user->id = 1;

        $result = $service->execute($this->loop(1), $user, 'session', false);

        $this->assertSame('rejected', $result['status']);
        $this->assertStringContainsString('confirmación', $result['error']['message']);
    }

    public function test_rejects_an_iteration_without_diagnosis_or_next_action(): void
    {
        $service = $this->service();
        $user = new User(['rol' => 'admin']);
        $user->id = 1;
        $loop = $this->loop(1);
        unset($loop['iterations'][0]['diagnosis'], $loop['iterations'][0]['next_action']);

        $result = $service->execute($loop, $user, 'session', true);

        $this->assertSame('rejected', $result['status']);
        $this->assertStringContainsString('diagnosis', $result['error']['message']);
    }

    private function service(): NexusAutomatedFixValidationLoopService
    {
        return new NexusAutomatedFixValidationLoopService(
            new NexusControlledCodeModificationService(new NexusToolRegistry()),
        );
    }

    private function loop(int $maxIterations): array
    {
        return [
            'objective' => 'Corregir y validar un fallo técnico.',
            'max_iterations' => $maxIterations,
            'iterations' => [[
                'objective' => 'Aplicar la corrección propuesta.',
                'plan' => [
                    'plan_id' => 'plan-loop',
                    'objective' => 'Aplicar la corrección propuesta.',
                    'status' => 'ready_for_authorization',
                    'executed' => false,
                    'required_tools' => ['nexus.code.modify'],
                    'required_permissions' => ['nexus.code.modify'],
                ],
                'change' => [
                    'path' => 'tests/example.php',
                    'expected_sha256' => str_repeat('a', 64),
                    'new_content' => '<?php',
                ],
                'tests' => ['files' => ['tests/example.php']],
                'diagnosis' => ['status' => 'supported', 'evidence' => ['test failure']],
                'next_action' => ['type' => 'stop'],
                'authorized' => true,
                'confirmed' => true,
            ]],
        ];
    }
}
