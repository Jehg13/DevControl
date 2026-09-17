<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $usuario_id
 * @property int|null $nexus_conversation_id
 * @property string|null $source
 * @property string|null $message
 * @property string|null $status
 * @property int|null $steps
 * @property array|null $context
 * @property array|null $internal_state
 * @property array|null $result
 * @property array|null $reflection
 * @property string|null $error
 */
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
        'reflection',
        'error',
    ];

    protected $casts = [
        'context' => 'array',
        'internal_state' => 'array',
        'result' => 'array',
        'reflection' => 'array',
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

    public function autonomousRun(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(NexusAutonomousRun::class);
    }

    public function recoveryCheckpoints(): HasMany
    {
        return $this->hasMany(NexusRecoveryCheckpoint::class);
    }
}
