<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NexusRun extends Model
{
    protected $table = 'nexus_runs';

    protected $fillable = [
        'usuario_id',
        'nexus_conversation_id',
        'source',
        'message',
        'status',
        'steps',
        'context',
        'internal_state',
        'result',
        'error',
    ];

    protected $casts = [
        'context' => 'array',
        'internal_state' => 'array',
        'result' => 'array',
    ];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(NexusConversation::class, 'nexus_conversation_id');
    }

    public function toolCalls(): HasMany
    {
        return $this->hasMany(NexusToolCall::class);
    }

    public function plan(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(NexusPlan::class);
    }
}
