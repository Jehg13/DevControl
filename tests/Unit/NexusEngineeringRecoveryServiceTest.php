<?php

namespace Tests\Unit;

use App\Models\NexusRun;
use App\Models\User;
use App\Services\NexusEngineeringRecoveryService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class NexusEngineeringRecoveryServiceTest extends TestCase
{
    private string $path;

    protected function setUp(): void
    {
        parent::setUp();
        $this->path = storage_path('framework/testing/recovery-phase-38.txt');
        File::ensureDirectoryExists(dirname($this->path));
        File::put($this->path, 'original');
    }

    protected function tearDown(): void
    {
        File::delete($this->path);
        parent::tearDown();
    }

    public function test_checkpoint_rolls_back_only_its_file_and_verifies_result(): void
    {
        $run = NexusRun::create([
            'message' => 'Recovery test',
            'source' => 'test',
            'status' => 'failed',
        ]);
        $service = new NexusEngineeringRecoveryService();
        $snapshot = $service->snapshotFile('storage/framework/testing/recovery-phase-38.txt');
        $checkpoint = $service->checkpoint($run, 'file-1', 'file_modification', [
            'path' => 'storage/framework/testing/recovery-phase-38.txt',
        ], $snapshot);
        File::put($this->path, 'changed');
        $checkpoint = $service->registerModifiedSnapshot($checkpoint, [
            'path' => 'storage/framework/testing/recovery-phase-38.txt',
            'original_content' => 'original',
            'original_sha256' => hash('sha256', 'original'),
            'modified_sha256' => hash('sha256', 'changed'),
        ]);

        $result = $service->rollback($checkpoint);

        $this->assertSame('rolled_back', $result['status']);
        $this->assertTrue($result['verified']);
        $this->assertSame('original', File::get($this->path));
    }

    public function test_hash_mismatch_stops_rollback_without_overwriting_external_changes(): void
    {
        $run = NexusRun::create(['message' => 'Recovery test', 'source' => 'test', 'status' => 'failed']);
        $service = new NexusEngineeringRecoveryService();
        $checkpoint = $service->checkpoint($run, 'file-1', 'file_modification', [], [
            'path' => 'storage/framework/testing/recovery-phase-38.txt',
            'exists' => true,
            'original_content' => 'original',
            'original_sha256' => hash('sha256', 'original'),
            'modified_sha256' => hash('sha256', 'changed'),
        ]);
        File::put($this->path, 'external-change');

        $result = $service->rollback($checkpoint);

        $this->assertSame('rollback_failed', $result['status']);
        $this->assertSame('external-change', File::get($this->path));
    }

    public function test_explicit_rollback_requires_admin_confirmation_and_session(): void
    {
        $run = NexusRun::create([
            'message' => 'Recovery test',
            'source' => 'test',
            'status' => 'failed',
            'context' => ['session_id' => 'session-1'],
        ]);
        $service = new NexusEngineeringRecoveryService();
        $user = new User(['rol' => 'usuario']);

        $result = $service->rollbackExplicitly($run, $user, 'session-1', true);

        $this->assertSame('rejected', $result['status']);
    }
}
