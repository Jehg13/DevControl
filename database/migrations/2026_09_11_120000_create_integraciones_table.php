<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->string('proveedor', 30)->default('github');
            $table->string('repositorio_url', 500);
            $table->string('repositorio_propietario', 150)->nullable();
            $table->string('repositorio_nombre', 150)->nullable();
            $table->string('rama_principal', 150)->nullable();
            $table->string('estado', 30)->default('pendiente');
            $table->timestamp('ultima_sincronizacion')->nullable();
            $table->timestamp('ultimo_intento')->nullable();
            $table->string('ultimo_commit_sha', 40)->nullable();
            $table->string('ultimo_commit_mensaje', 500)->nullable();
            $table->text('ultimo_error')->nullable();
            $table->timestamps();

            $table->unique(['proyecto_id', 'proveedor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integraciones');
    }
};
