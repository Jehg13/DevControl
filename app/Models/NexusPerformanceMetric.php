<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexusPerformanceMetric extends Model
{
    protected $table = 'nexus_performance_metrics';

    protected $fillable = [
        'nexus_run_id',
        'objective_hash',
        'project_id',
        'status',
        'duration_ms',
        'steps',
        'tool_calls',
        'failed_calls',
        'retries',
        'memory_items',
        'redundant_tools',
        'metrics',
    ];

    protected $casts = [
        'redundant_tools' => 'array',
        'metrics' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(NexusRun::class, 'nexus_run_id');
    }
}
