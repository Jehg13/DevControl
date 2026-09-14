<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_code_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->string('path', 500);
            $table->string('fingerprint', 64);
            $table->string('language', 40);
            $table->json('analysis');
            $table->string('status', 30)->default('active');
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();

            $table->unique(['proyecto_id', 'path']);
            $table->index(['proyecto_id', 'fingerprint']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_code_files');
    }
};
