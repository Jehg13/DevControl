<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexusProactiveAlert extends Model
{
    protected $table = 'nexus_proactive_alerts';

    protected $fillable = [
        'proyecto_id', 'source', 'group_key', 'fingerprint', 'priority', 'status',
        'occurrences', 'first_seen', 'last_seen', 'last_notified_at',
        'silenced_until', 'evidence', 'metadata',
    ];

    protected $casts = [
        'first_seen' => 'datetime',
        'last_seen' => 'datetime',
        'last_notified_at' => 'datetime',
        'silenced_until' => 'datetime',
        'evidence' => 'array',
        'metadata' => 'array',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }
}
