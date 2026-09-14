<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_experiences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->foreignId('nexus_run_id')->nullable()->constrained('nexus_runs')->nullOnDelete();
            $table->text('problem');
            $table->json('context')->nullable();
            $table->text('hypothesis')->nullable();
            $table->text('action')->nullable();
            $table->json('result')->nullable();
            $table->text('solution')->nullable();
            $table->text('lesson')->nullable();
            $table->json('errors')->nullable();
            $table->text('strategy')->nullable();
            $table->json('tools_used')->nullable();
            $table->json('reflection')->nullable();
            $table->json('technologies')->nullable();
            $table->string('category', 80)->default('general');
            $table->unsignedTinyInteger('relevance')->default(50);
            $table->unsignedTinyInteger('confidence')->default(50);
            $table->string('status', 30)->default('active');
            $table->text('correction')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['proyecto_id', 'status', 'relevance']);
            $table->index(['category', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_experiences');
    }
};
