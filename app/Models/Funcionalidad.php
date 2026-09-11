<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Funcionalidad extends Model
{
    use HasFactory;

    protected $table = 'funcionalidades';

    protected $fillable = [
        'seccion_id',
        'nombre',
        'descripcion',
        'estado',
        'orden',
    ];

    public function seccion(): BelongsTo
    {
        return $this->belongsTo(Seccion::class);
    }
}
