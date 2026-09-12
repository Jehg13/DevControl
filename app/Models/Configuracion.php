<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $fillable = ['clave', 'valor', 'sensible'];

    protected $casts = ['sensible' => 'boolean'];

    public static function valor(string $clave, mixed $default = null): mixed
    {
        $configuracion = static::query()->where('clave', $clave)->first();

        return $configuracion?->valor ?? $default;
    }

    public static function guardar(string $clave, mixed $valor, bool $sensible = false): self
    {
        return static::query()->updateOrCreate(
            ['clave' => $clave],
            ['valor' => $valor === null ? null : (string) $valor, 'sensible' => $sensible]
        );
    }
}
