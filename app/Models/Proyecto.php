<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Proyecto extends Model
{
    use HasFactory;

    protected $table = 'proyectos';

    protected $fillable = [
        'nombre',
        'descripcion',
        'contexto',
        'objetivo',
        'tecnologias',
        'reglas',
        'repositorio_url',
        'fecha_inicio',
        'fecha_meta',
        'estado',
        'progreso',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_meta' => 'date',
        'progreso' => 'integer',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELACIONES
    |--------------------------------------------------------------------------
    */

    public function tareas()
    {
        return $this->hasMany(Tarea::class, 'proyecto_id');
    }

    public function bugs()
    {
        return $this->hasMany(Bug::class, 'proyecto_id');
    }

    public function incidentes()
    {
        return $this->hasMany(Incidente::class, 'proyecto_id');
    }

    public function actualizaciones()
    {
        return $this->hasMany(Actualizacion::class, 'proyecto_id');
    }

    public function secciones()
    {
        return $this->hasMany(Seccion::class)->orderBy('orden');
    }

    public function integracionGithub()
    {
        return $this->hasOne(Integracion::class)->where('proveedor', 'github');
    }
}
