<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NexusFinding extends Model
{
    protected $table = 'nexus_hallazgos';

    protected $fillable = [
        'proyecto_id', 'tipo', 'severidad', 'huella', 'titulo',
        'descripcion', 'metadata', 'estado', 'detectado_en',
    ];

    protected $casts = [
        'metadata' => 'array',
        'detectado_en' => 'datetime',
    ];

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }
}
