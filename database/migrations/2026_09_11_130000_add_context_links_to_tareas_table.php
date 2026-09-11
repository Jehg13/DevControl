<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->foreignId('seccion_id')
                ->nullable()
                ->after('proyecto_id')
                ->constrained('secciones')
                ->nullOnDelete();
            $table->foreignId('funcionalidad_id')
                ->nullable()
                ->after('seccion_id')
                ->constrained('funcionalidades')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tareas', function (Blueprint $table) {
            $table->dropForeign(['funcionalidad_id']);
            $table->dropForeign(['seccion_id']);
            $table->dropColumn(['funcionalidad_id', 'seccion_id']);
        });
    }
};
