<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_project_understandings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_path', 300)->default('.');
            $table->string('project_name', 190);
            $table->string('source_fingerprint', 64);
            $table->json('understanding');
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();

            $table->index(['proyecto_id', 'source_path']);
            $table->index(['source_fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_project_understandings');
    }
};
