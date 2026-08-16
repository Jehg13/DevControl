<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actualizaciones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('proyecto_id')
                  ->constrained('proyectos')
                  ->cascadeOnDelete();

            $table->string('titulo');

            $table->text('detalles');

            $table->string('commit', 40)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('actualizaciones');
    }
};