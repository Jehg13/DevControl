<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexusCodeFile extends Model
{
    protected $table = 'nexus_code_files';

    protected $fillable = [
        'proyecto_id', 'path', 'fingerprint', 'language', 'analysis',
        'status', 'invalidated_at',
    ];

    protected $casts = [
        'analysis' => 'array',
        'invalidated_at' => 'datetime',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }
}
