<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_hallazgos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->string('tipo', 40);
            $table->string('severidad', 20)->default('info');
            $table->string('huella', 64)->unique();
            $table->string('titulo', 190);
            $table->text('descripcion');
            $table->json('metadata')->nullable();
            $table->string('estado', 20)->default('activo');
            $table->dateTime('detectado_en');
            $table->timestamps();
            $table->index(['proyecto_id', 'estado']);
            $table->index(['tipo', 'severidad']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_hallazgos');
    }
};
