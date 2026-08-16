<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tareas', function (Blueprint $table) {

            $table->id();

            $table->foreignId('proyecto_id')
                  ->constrained('proyectos')
                  ->cascadeOnDelete();

            $table->string('titulo');

            $table->text('descripcion')->nullable();

            $table->enum('prioridad', [
                'Alta',
                'Media',
                'Baja'
            ])->default('Media');

            $table->enum('estado', [
                'Pendiente',
                'En progreso',
                'En revisión',
                'Completado',
                'Cancelado'
            ])->default('Pendiente');

            $table->date('fecha_inicio')->nullable();

            $table->date('fecha_limite')->nullable();

            $table->date('fecha_completada')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tareas');
    }
};