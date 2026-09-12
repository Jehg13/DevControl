<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origen', 30)->default('Usuario');
            $table->string('accion');
            $table->text('descripcion');
            $table->string('entidad', 80)->nullable();
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->json('metadatos')->nullable();
            $table->timestamps();

            $table->index(['entidad', 'entidad_id']);
            $table->index('origen');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actividades');
    }
};
