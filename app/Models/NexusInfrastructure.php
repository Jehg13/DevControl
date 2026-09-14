<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NexusInfrastructure extends Model
{
    protected $table = 'nexus_infrastructures';

    protected $fillable = [
        'proyecto_id',
        'usuario_id',
        'name',
        'provider',
        'server',
        'operating_system',
        'resources',
        'services',
        'applications',
        'databases',
        'domains',
        'ssl',
        'health',
        'last_check',
        'metadata',
    ];

    protected $casts = [
        'server' => 'array',
        'operating_system' => 'array',
        'resources' => 'array',
        'services' => 'array',
        'applications' => 'array',
        'databases' => 'array',
        'domains' => 'array',
        'ssl' => 'array',
        'health' => 'array',
        'last_check' => 'datetime',
        'metadata' => 'array',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(NexusInfrastructureEvent::class, 'infrastructure_id');
    }
}
