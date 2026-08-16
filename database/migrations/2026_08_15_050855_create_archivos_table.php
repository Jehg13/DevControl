<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('archivos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->cascadeOnDelete();

            $table->foreignId('carpeta_id')
                ->nullable()
                ->constrained('carpetas')
                ->nullOnDelete();

            $table->string('nombre', 255);

            $table->text('descripcion')
                ->nullable();

            $table->string('archivo', 500);

            $table->string('tipo', 50);

            $table->unsignedBigInteger('peso');

            $table->string('version', 50)
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('archivos');
    }
};