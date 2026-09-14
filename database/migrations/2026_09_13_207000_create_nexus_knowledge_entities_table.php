<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_knowledge_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->string('entity_type', 80);
            $table->string('entity_key', 500);
            $table->string('label', 500);
            $table->json('metadata')->nullable();
            $table->string('status', 30)->default('active');
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();

            $table->unique(['entity_type', 'entity_key']);
            $table->index(['proyecto_id', 'entity_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_knowledge_entities');
    }
};
