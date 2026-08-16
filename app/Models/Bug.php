<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function proyecto()
    {
        return $this->belongsTo(Proyecto::class, 'proyecto_id');
    }
}