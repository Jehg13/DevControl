<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tarea extends Model
{
    protected $table = 'tareas';

    protected $fillable = [
        'proyecto_id',
        'titulo',
        'descripcion',
        'prioridad',
        'estado',
        'fecha_inicio',
        'fecha_limite',
        'fecha_completada',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_limite' => 'date',
        'fecha_completada' => 'date',
    ];

    /**
     * Una tarea pertenece a un proyecto.
     */
    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }
}