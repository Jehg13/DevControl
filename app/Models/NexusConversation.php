<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NexusConversation extends Model
{
    protected $table = 'nexus_conversations';

    protected $fillable = ['usuario_id', 'session_key', 'title', 'last_activity_at'];

    protected $casts = ['last_activity_at' => 'datetime'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(NexusMessage::class);
    }
}
