<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexusPlan extends Model
{
    protected $table = 'nexus_plans';

    protected $fillable = [
        'nexus_run_id',
        'objective',
        'subtasks',
        'status',
        'priority',
        'expected_result',
        'completion_criteria',
        'metadata',
    ];

    protected $casts = [
        'subtasks' => 'array',
        'metadata' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(NexusRun::class, 'nexus_run_id');
    }
}
