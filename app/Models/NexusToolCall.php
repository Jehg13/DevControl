<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexusToolCall extends Model
{
    protected $table = 'nexus_tool_calls';

    protected $fillable = [
        'nexus_run_id',
        'sequence',
        'tool_name',
        'arguments',
        'status',
        'result',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'arguments' => 'array',
        'result' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(NexusRun::class, 'nexus_run_id');
    }
}
