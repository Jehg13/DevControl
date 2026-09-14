<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexusKnowledgeClaim extends Model
{
    protected $table = 'nexus_knowledge_claims';

    protected $fillable = [
        'proyecto_id', 'subject_entity_id', 'object_entity_id', 'predicate',
        'object_value', 'epistemic_type', 'confidence', 'source_type',
        'source_id', 'source_fingerprint', 'version', 'supersedes_id',
        'status', 'invalidated_at', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'invalidated_at' => 'datetime',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(NexusKnowledgeEntity::class, 'subject_entity_id');
    }

    public function object(): BelongsTo
    {
        return $this->belongsTo(NexusKnowledgeEntity::class, 'object_entity_id');
    }
}
