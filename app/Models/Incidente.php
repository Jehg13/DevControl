<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\DevControlAlertService;

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

    protected static function booted(): void
    {
        static::created(function (Incidente $incidente): void {
            Actividad::registrar('Incidente creado', "Se registró el incidente: {$incidente->titulo}.", $incidente);
            app(DevControlAlertService::class)->send(
                'Nuevo incidente registrado',
                $incidente->titulo,
                $incidente->proyecto,
                [
                    'Prioridad' => $incidente->prioridad,
                    'Estado' => $incidente->estado,
                    'Descripción' => $incidente->descripcion ?: 'Sin descripción',
                ]
            );
        });

        static::updated(function (Incidente $incidente): void {
            Actividad::registrar('Incidente actualizado', "Se actualizó el incidente: {$incidente->titulo}.", $incidente, ['cambios' => $incidente->getChanges()]);
            if (! $incidente->wasChanged(['estado', 'prioridad'])) {
                return;
            }

            app(DevControlAlertService::class)->send(
                'Incidente actualizado',
                $incidente->titulo,
                $incidente->proyecto,
                [
                    'Prioridad' => $incidente->prioridad,
                    'Estado' => $incidente->estado,
                ]
            );
        });
    }

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }
}
