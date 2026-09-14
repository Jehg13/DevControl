<?php

namespace Tests\Unit;

use App\Contracts\NexusModel;
use App\Nexus\NexusModelRequest;
use App\Nexus\NexusModelResponse;
use App\Services\NexusPlanService;
use App\Services\NexusPlannerService;
use App\Services\NexusProjectUnderstandingService;
use App\Services\NexusGithubService;
use App\Services\NexusPermissionManager;
use App\Services\NexusExperienceService;
use Tests\TestCase;

class NexusPlannerServiceTest extends TestCase
{
    public function test_it_sends_planning_inputs_and_returns_structured_steps_without_tool_calls(): void
    {
        $captured = null;
        $model = new class($captured) implements NexusModel
        {
            public function __construct(private mixed &$captured)
            {
            }

            public function complete(NexusModelRequest $request): NexusModelResponse
            {
                $this->captured = $request;

                return new NexusModelResponse(
                    'plan',
                    'Plan creado.',
                    [],
                    false,
                    [
                        'objective' => 'Agregar recuperación de contraseña.',
                        'subtasks' => [[
                            'id' => 'inspect-auth',
                            'title' => 'Analizar autenticación',
                            'dependencies' => [],
                            'required_tools' => ['nexus.project.query'],
                            'status' => 'pending',
                        ]],
                    ]
                );
            }
        };

        $planner = new NexusPlannerService(
            $model,
            app(NexusPlanService::class),
            app(NexusProjectUnderstandingService::class),
            app(NexusGithubService::class),
            app(NexusPermissionManager::class),
            app(NexusExperienceService::class),
        );

        app(NexusExperienceService::class)->remember([
            'problem' => 'La autenticación de Laravel falla después de cambiar rutas',
            'solution' => 'Revisar middleware y configuración de sesión',
            'lesson' => 'Validar middleware antes de modificar controladores',
            'technologies' => ['Laravel'],
            'relevance' => 80,
            'confidence' => 80,
        ]);

        $result = $planner->create(
            'Agregar recuperación de contraseña.',
            ['known' => ['Laravel']],
            ['name' => 'DevControl'],
            ['no ejecutar cambios'],
            ['nexus.read'],
            [['name' => 'nexus.project.query']]
        );

        $this->assertTrue($result->successful);
        $this->assertSame('Agregar recuperación de contraseña.', $captured->context['planner']['objective']);
        $this->assertSame(['no ejecutar cambios'], $captured->context['planner']['restrictions']);
        $this->assertSame(['nexus.read'], $captured->context['planner']['permissions']);
        $this->assertSame([['name' => 'nexus.project.query']], $captured->tools);
        $this->assertNotEmpty($captured->context['similar_experiences']);
        $this->assertSame([], $result->response->toolCalls);
        $this->assertSame('inspect-auth', $result->response->metadata['subtasks'][0]['id']);
    }
}
