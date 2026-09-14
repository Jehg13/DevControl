<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NexusKnowledgeEntity extends Model
{
    protected $table = 'nexus_knowledge_entities';

    protected $fillable = [
        'proyecto_id', 'entity_type', 'entity_key', 'label', 'metadata',
        'status', 'invalidated_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'invalidated_at' => 'datetime',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(NexusKnowledgeClaim::class, 'subject_entity_id');
    }
}
