<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class NexusRecoveryCheckpoint extends Model
{
    protected $table = 'nexus_recovery_checkpoints';

    protected $fillable = [
        'nexus_run_id',
        'operation_key',
        'operation_type',
        'scope',
        'snapshot',
        'status',
        'rollback_status',
        'rollback_result',
        'error',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'scope' => 'array',
        'snapshot' => 'array',
        'rollback_result' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(NexusRun::class, 'nexus_run_id');
    }
}
