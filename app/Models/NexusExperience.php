<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexusExperience extends Model
{
    protected $table = 'nexus_experiences';

    protected $fillable = [
        'usuario_id',
        'proyecto_id',
        'nexus_run_id',
        'problem',
        'context',
        'hypothesis',
        'action',
        'result',
        'solution',
        'lesson',
        'errors',
        'strategy',
        'tools_used',
        'reflection',
        'technologies',
        'category',
        'relevance',
        'confidence',
        'status',
        'correction',
        'metadata',
    ];

    protected $casts = [
        'context' => 'array',
        'result' => 'array',
        'errors' => 'array',
        'tools_used' => 'array',
        'reflection' => 'array',
        'technologies' => 'array',
        'relevance' => 'integer',
        'confidence' => 'integer',
        'metadata' => 'array',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(NexusRun::class, 'nexus_run_id');
    }
}
