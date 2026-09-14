<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_github_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->constrained('proyectos')->cascadeOnDelete();
            $table->string('status', 30)->default('pending');
            $table->string('branch', 150)->nullable();
            $table->json('files')->nullable();
            $table->unsignedInteger('cursor')->default(0);
            $table->unsignedInteger('total_files')->default(0);
            $table->unsignedInteger('processed_files')->default(0);
            $table->unsignedInteger('skipped_files')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['proyecto_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_github_analyses');
    }
};
