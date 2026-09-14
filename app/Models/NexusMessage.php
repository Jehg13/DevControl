<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NexusMessage extends Model
{
    protected $table = 'nexus_messages';

    protected $fillable = ['nexus_conversation_id', 'role', 'content', 'metadata'];

    protected $casts = ['metadata' => 'array'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(NexusConversation::class, 'nexus_conversation_id');
    }
}
