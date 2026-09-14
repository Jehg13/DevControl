<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexusAutonomousRun extends Model
{
    protected $table = 'nexus_autonomous_runs';

    protected $fillable = [
        'nexus_run_id',
        'nexus_plan_id',
        'usuario_id',
        'proyecto_id',
        'objective',
        'status',
        'plan',
        'limits',
        'state',
        'steps',
        'retries',
        'duration_ms',
        'result',
        'error',
        'started_at',
        'paused_at',
        'finished_at',
    ];

    protected $casts = [
        'plan' => 'array',
        'limits' => 'array',
        'state' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'paused_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(NexusRun::class, 'nexus_run_id');
    }

    public function planModel(): BelongsTo
    {
        return $this->belongsTo(NexusPlan::class, 'nexus_plan_id');
    }
}
