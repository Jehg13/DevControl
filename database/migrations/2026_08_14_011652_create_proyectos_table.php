<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('proyectos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->text('descripcion')->nullable();
            $table->text('contexto')->nullable();
            $table->text('objetivo')->nullable();
            $table->text('tecnologias')->nullable();
            $table->text('reglas')->nullable();
            $table->string('repositorio_url', 500)->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_meta')->nullable();
            $table->string('estado', 30)->default('activo');
            $table->unsignedTinyInteger('progreso')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('proyectos');
    }
};
