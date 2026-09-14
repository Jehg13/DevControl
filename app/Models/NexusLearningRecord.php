<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexusLearningRecord extends Model
{
    protected $table = 'nexus_learning_records';

    protected $fillable = [
        'learning_key', 'learning_type', 'status', 'pattern', 'observation',
        'evidence', 'proposed_update', 'applied_update', 'rollback_snapshot',
        'confidence', 'proyecto_id', 'source_type', 'source_id',
        'validated_at', 'rejected_at', 'rolled_back_at',
    ];

    protected $casts = [
        'observation' => 'array',
        'evidence' => 'array',
        'proposed_update' => 'array',
        'applied_update' => 'array',
        'rollback_snapshot' => 'array',
        'confidence' => 'integer',
        'validated_at' => 'datetime',
        'rejected_at' => 'datetime',
        'rolled_back_at' => 'datetime',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }
}
