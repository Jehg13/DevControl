<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexusGithubAnalysis extends Model
{
    protected $table = 'nexus_github_analyses';

    protected $fillable = [
        'proyecto_id', 'status', 'branch', 'files', 'cursor', 'total_files',
        'processed_files', 'skipped_files', 'error', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'files' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }
}
