<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Actualizacion extends Model
{
    use HasFactory;

    protected $table = 'actualizaciones';

    protected $fillable = [
        'proyecto_id',
        'titulo',
        'detalle',
        'commit',
        'fecha',
    ];

    protected $casts = [
        'fecha' => 'date',
    ];

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }
}