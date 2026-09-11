<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Incidente extends Model
{
    protected $table = 'incidentes';

    protected $fillable = [
        'proyecto_id',
        'titulo',
        'descripcion',
        'prioridad',
        'estado',
        'fecha_detectado',
    ];

    protected $casts = [
        'fecha_detectado' => 'date',
    ];

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }
}
