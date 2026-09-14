<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexusMemory extends Model
{
    protected $table = 'nexus_memories';

    protected $fillable = [
        'usuario_id',
        'nexus_conversation_id',
        'proyecto_id',
        'memory_key',
        'content',
        'source',
        'importance',
        'metadata',
        'last_used_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'last_used_at' => 'datetime',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(NexusConversation::class, 'nexus_conversation_id');
    }
}
