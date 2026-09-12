<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\DevControlAlertService;

class Bug extends Model
{
    use HasFactory;

    protected $table = 'bugs';

    protected $fillable = [
        'proyecto_id',
        'folio',
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
        static::created(function (Bug $bug): void {
            Actividad::registrar('Bug creado', "Se registró el bug {$bug->folio}: {$bug->titulo}.", $bug);
            app(DevControlAlertService::class)->send(
                'Nuevo bug registrado',
                $bug->titulo,
                $bug->proyecto,
                [
                    'Folio' => $bug->folio,
                    'Prioridad' => $bug->prioridad,
                    'Estado' => $bug->estado,
                    'Descripción' => $bug->descripcion ?: 'Sin descripción',
                ]
            );
        });

        static::updated(function (Bug $bug): void {
            Actividad::registrar('Bug actualizado', "Se actualizó {$bug->folio}: {$bug->titulo}.", $bug, ['cambios' => $bug->getChanges()]);
            if (! $bug->wasChanged(['estado', 'prioridad'])) {
                return;
            }

            app(DevControlAlertService::class)->send(
                'Bug actualizado',
                $bug->titulo,
                $bug->proyecto,
                [
                    'Folio' => $bug->folio,
                    'Prioridad' => $bug->prioridad,
                    'Estado' => $bug->estado,
                ]
            );
        });
    }

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }
}