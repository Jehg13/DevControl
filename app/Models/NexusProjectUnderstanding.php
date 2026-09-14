<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexusProjectUnderstanding extends Model
{
    protected $table = 'nexus_project_understandings';

    protected $fillable = [
        'proyecto_id',
        'usuario_id',
        'source_path',
        'project_name',
        'source_fingerprint',
        'understanding',
        'analyzed_at',
        'invalidated_at',
    ];

    protected $casts = [
        'understanding' => 'array',
        'analyzed_at' => 'datetime',
        'invalidated_at' => 'datetime',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }
}
