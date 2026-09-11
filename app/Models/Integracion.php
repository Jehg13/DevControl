<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Integracion extends Model
{
    use HasFactory;

    protected $table = 'integraciones';

    protected $fillable = [
        'proyecto_id',
        'proveedor',
        'repositorio_url',
        'repositorio_propietario',
        'repositorio_nombre',
        'rama_principal',
        'estado',
        'ultima_sincronizacion',
        'ultimo_intento',
        'ultimo_commit_sha',
        'ultimo_commit_mensaje',
        'ultimo_error',
    ];

    protected $casts = [
        'ultima_sincronizacion' => 'datetime',
        'ultimo_intento' => 'datetime',
    ];

    public function proyecto(): BelongsTo
    {
        return $this->belongsTo(Proyecto::class);
    }
}
