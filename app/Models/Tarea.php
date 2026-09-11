<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tarea extends Model
{
    protected $table = 'tareas';

    protected $fillable = [
        'proyecto_id',
        'usuario_id',
        'seccion_id',
        'funcionalidad_id',
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

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function seccion()
    {
        return $this->belongsTo(Seccion::class, 'seccion_id');
    }

    public function funcionalidad()
    {
        return $this->belongsTo(Funcionalidad::class, 'funcionalidad_id');
    }
}
