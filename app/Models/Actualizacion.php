<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Services\DevControlAlertService;

class Actualizacion extends Model
{
    use HasFactory;

    protected $table = 'actualizaciones';

    protected $fillable = [
        'proyecto_id',
        'titulo',
        'detalles',
        'detalle',
        'commit',
        'fecha',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    protected static function booted(): void
    {
        static::created(function (Actualizacion $actualizacion): void {
            Actividad::registrar('Actualización registrada', $actualizacion->titulo, $actualizacion);
            app(DevControlAlertService::class)->send(
                'Nueva actualización registrada',
                $actualizacion->titulo,
                $actualizacion->proyecto,
                [
                    'Detalles' => $actualizacion->detalles ?: $actualizacion->detalle ?: 'Sin detalles',
                    'Commit' => $actualizacion->commit ?: 'No asociado',
                ]
            );
        });
    }

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }
}
