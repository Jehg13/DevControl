<?php

namespace Tests\Unit;

use App\Services\NexusProjectUnderstandingService;
use ReflectionMethod;
use Tests\TestCase;

class NexusProjectArchitectureTest extends TestCase
{
    public function test_reconstructs_project_flow_with_direct_and_inferred_edges(): void
    {
        $service = app(NexusProjectUnderstandingService::class);
        $method = new ReflectionMethod($service, 'buildUnderstanding');
        $method->setAccessible(true);

        $understanding = $method->invoke($service, [
            'files' => [
                ['path' => 'routes/web.php'],
                ['path' => 'app/Http/Controllers/ProyectoController.php'],
                ['path' => 'app/Http/Requests/StoreProyectoRequest.php'],
                ['path' => 'app/Services/ProyectoService.php'],
                ['path' => 'app/Models/Proyecto.php'],
                ['path' => 'resources/views/admin/proyectos.blade.php'],
                ['path' => 'database/migrations/2026_create_proyectos_table.php'],
            ],
            'structure' => [],
            'technologies' => ['detected' => ['PHP', 'Laravel']],
            'route_bindings' => [[
                'route' => '/dashboard/proyectos',
                'file' => 'routes/web.php',
                'controller' => 'App\Http\Controllers\ProyectoController',
                'action' => 'index',
            ]],
            'relationships' => [],
            'dependencies' => [],
            'fingerprint' => 'test',
        ], null, '.');

        $map = $understanding['architecture_map'];
        $this->assertSame('direct', collect($map['edges'])->firstWhere('type', 'route_to_controller')['certainty']);
        $this->assertSame('inference', collect($map['edges'])->firstWhere('type', 'may_use')['certainty']);
        $this->assertStringContainsString('La ruta /dashboard/proyectos entra por', $map['narrative']);
        $this->assertStringContainsString('migraciones', $map['narrative']);
    }

    public function test_marks_unfound_architecture_components_without_inventing_them(): void
    {
        $service = app(NexusProjectUnderstandingService::class);
        $method = new ReflectionMethod($service, 'buildUnderstanding');
        $method->setAccessible(true);

        $understanding = $method->invoke($service, [
            'files' => [
                ['path' => 'routes/web.php'],
                ['path' => 'app/Http/Controllers/ProyectoController.php'],
            ],
            'structure' => [],
            'technologies' => ['detected' => ['PHP', 'Laravel']],
            'route_bindings' => [],
            'relationships' => [],
            'dependencies' => [],
            'fingerprint' => 'test',
        ], null, '.');

        $components = collect($understanding['architecture_map']['components'])->keyBy('type');
        $this->assertSame('not_found', $components['jobs']['status']);
        $this->assertSame([], $components['jobs']['files']);
        $this->assertSame('not_found', $components['events']['status']);
        $this->assertSame('No se encontraron bindings de rutas suficientes para reconstruir un flujo HTTP.', $understanding['architecture_map']['narrative']);
    }
}
