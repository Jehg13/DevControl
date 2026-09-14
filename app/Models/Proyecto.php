<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\DevControlAlertService;
use App\Models\NexusCodeFile;

/**
 * @property int $id
 * @property string $nombre
 * @property string|null $descripcion
 * @property string|null $contexto
 * @property string|null $objetivo
 * @property string|null $tecnologias
 * @property string|null $reglas
 * @property string|null $repositorio_url
 * @property \Illuminate\Support\Carbon|null $fecha_inicio
 * @property \Illuminate\Support\Carbon|null $fecha_meta
 * @property int|null $progreso
 */
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

    protected static function booted(): void
    {
        static::created(function (Proyecto $proyecto): void {
            Actividad::registrar('Proyecto creado', "Se registró el proyecto: {$proyecto->nombre}.", $proyecto);
            app(DevControlAlertService::class)->send(
                'Nuevo proyecto registrado',
                $proyecto->nombre,
                $proyecto,
                [
                    'Estado' => $proyecto->estado,
                    'Repositorio' => $proyecto->repositorio_url ?: 'No configurado',
                ]
            );
        });
    }

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

    public function nexusUnderstandings()
    {
        return $this->hasMany(NexusProjectUnderstanding::class, 'proyecto_id');
    }

    public function nexusInfrastructures()
    {
        return $this->hasMany(NexusInfrastructure::class, 'proyecto_id');
    }

    public function nexusGithubAnalyses()
    {
        return $this->hasMany(NexusGithubAnalysis::class, 'proyecto_id');
    }

    public function nexusCodeFiles()
    {
        return $this->hasMany(NexusCodeFile::class, 'proyecto_id');
    }
}
