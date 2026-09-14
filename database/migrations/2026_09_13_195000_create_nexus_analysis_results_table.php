<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_analysis_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->foreignId('nexus_run_id')->nullable()->constrained('nexus_runs')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 190);
            $table->string('analysis_type', 50)->default('code');
            $table->json('result');
            $table->timestamps();

            $table->index(['proyecto_id', 'analysis_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_analysis_results');
    }
};
