<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carpetas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('proyecto_id')
                ->constrained('proyectos')
                ->cascadeOnDelete();

            $table->string('nombre', 150);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carpetas');
    }
};