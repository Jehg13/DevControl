<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bugs', function (Blueprint $table) {
            $table->id();

            $table->string('folio', 20)->unique();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->cascadeOnDelete();

            $table->string('titulo');

            $table->text('descripcion');

            $table->enum('estado', [
                'Reportado',
                'Investigando',
                'En desarrollo',
                'En pruebas',
                'Solucionado',
                'Cerrado'
            ])->default('Reportado');

            $table->enum('prioridad', [
                'Baja',
                'Media',
                'Alta'
            ])->default('Media');

            $table->dateTime('fecha_detectado');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bugs');
    }
};