<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Actividad extends Model
{
    protected $table = 'actividades';

    protected $fillable = [
        'proyecto_id',
        'usuario_id',
        'origen',
        'accion',
        'descripcion',
        'entidad',
        'entidad_id',
        'metadatos',
    ];

    protected $casts = ['metadatos' => 'array'];

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }

    public static function registrar(string $accion, string $descripcion, ?Model $entidad = null, array $metadatos = []): self
    {
        $proyecto = $entidad instanceof Proyecto ? $entidad : $entidad?->proyecto;

        return static::create([
            'proyecto_id' => $proyecto?->id,
            'usuario_id' => auth()->id(),
            'origen' => auth()->check() ? 'Usuario' : 'DevControl',
            'accion' => $accion,
            'descripcion' => $descripcion,
            'entidad' => $entidad ? get_class($entidad) : null,
            'entidad_id' => $entidad?->getKey(),
            'metadatos' => $metadatos,
        ]);
    }
}
