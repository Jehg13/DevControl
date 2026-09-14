<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\DevControlAlertService;

/**
 * @property int $id
 * @property int|null $proyecto_id
 * @property int|null $usuario_id
 * @property int|null $seccion_id
 * @property int|null $funcionalidad_id
 * @property string $titulo
 * @property string|null $descripcion
 * @property string|null $prioridad
 * @property string|null $estado
 * @property \Illuminate\Support\Carbon|null $fecha_inicio
 * @property \Illuminate\Support\Carbon|null $fecha_limite
 * @property \Illuminate\Support\Carbon|null $fecha_completada
 */
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

    protected static function booted(): void
    {
        static::created(function (Tarea $tarea): void {
            Actividad::registrar('Tarea creada', "Se registró la tarea: {$tarea->titulo}.", $tarea);
            app(DevControlAlertService::class)->send(
                'Nueva tarea registrada',
                $tarea->titulo,
                $tarea->proyecto,
                [
                    'Prioridad' => $tarea->prioridad,
                    'Estado' => $tarea->estado,
                    'Fecha límite' => $tarea->fecha_limite ? (string) $tarea->fecha_limite : 'Sin fecha límite',
                ]
            );
        });

        static::updated(function (Tarea $tarea): void {
            Actividad::registrar('Tarea actualizada', "Se actualizó la tarea: {$tarea->titulo}.", $tarea, ['cambios' => $tarea->getChanges()]);
            if (! $tarea->wasChanged(['estado', 'prioridad', 'fecha_limite'])) {
                return;
            }

            app(DevControlAlertService::class)->send(
                'Tarea actualizada',
                $tarea->titulo,
                $tarea->proyecto,
                [
                    'Prioridad' => $tarea->prioridad,
                    'Estado' => $tarea->estado,
                    'Fecha límite' => $tarea->fecha_limite ? (string) $tarea->fecha_limite : 'Sin fecha límite',
                ]
            );
        });
    }

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
