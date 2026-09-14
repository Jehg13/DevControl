<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexusDatasetExample extends Model
{
    protected $table = 'nexus_dataset_examples';

    protected $fillable = [
        'example_hash', 'version', 'split', 'payload', 'labels', 'quality_score',
        'status', 'source_type', 'source_id', 'proyecto_id', 'invalidated_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'labels' => 'array',
        'quality_score' => 'integer',
        'invalidated_at' => 'datetime',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }
}
