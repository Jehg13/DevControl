<?php

namespace Tests\Unit;

use App\Models\NexusCodeFile;
use App\Services\NexusCodeIntelligenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NexusCodeIntelligenceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_indexes_php_symbols_dependencies_and_queries_without_a_model(): void
    {
        $result = app(NexusCodeIntelligenceService::class)->analyze();
        $php = collect($result['files'])->first(fn (array $file): bool => $file['language'] === 'PHP' && $file['path'] === 'app/Services/NexusKnowledgeGraphService.php');

        $this->assertNotNull($php);
        $this->assertContains('NexusKnowledgeGraphService', array_column($php['symbols']['classes'], 'name'));
        $this->assertContains('relate', $php['symbols']['methods']);
        $this->assertContains('App\Models\NexusKnowledgeClaim', $php['symbols']['imports']);
        $this->assertIsArray($php['symbols']['queries']);
        $this->assertContains('PHP', $result['languages']);
        $this->assertTrue($result['incremental']);
    }

    public function test_it_reuses_unchanged_files_on_incremental_analysis(): void
    {
        $service = app(NexusCodeIntelligenceService::class);
        $first = $service->analyze();
        $second = $service->analyze();

        $this->assertNotEmpty($first['changed_files']);
        $this->assertEmpty($second['changed_files']);
        $this->assertNotEmpty($second['unchanged_files']);
        $this->assertSame(count($second['files']), NexusCodeFile::where('status', 'active')->count());
    }
}
