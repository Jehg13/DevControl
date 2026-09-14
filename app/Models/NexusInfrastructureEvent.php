<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexusInfrastructureEvent extends Model
{
    protected $table = 'nexus_infrastructure_events';

    protected $fillable = [
        'infrastructure_id',
        'proyecto_id',
        'type',
        'severity',
        'title',
        'description',
        'evidence',
        'detected_at',
        'resolved_at',
    ];

    protected $casts = [
        'evidence' => 'array',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function infrastructure(): BelongsTo
    {
        return $this->belongsTo(NexusInfrastructure::class, 'infrastructure_id');
    }
}
