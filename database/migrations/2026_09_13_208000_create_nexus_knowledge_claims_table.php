<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_knowledge_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->foreignId('subject_entity_id')->constrained('nexus_knowledge_entities')->cascadeOnDelete();
            $table->foreignId('object_entity_id')->nullable()->constrained('nexus_knowledge_entities')->nullOnDelete();
            $table->string('predicate', 150);
            $table->text('object_value')->nullable();
            $table->string('epistemic_type', 30);
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->string('source_type', 100);
            $table->string('source_id', 190)->nullable();
            $table->string('source_fingerprint', 128)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('supersedes_id')->nullable()->constrained('nexus_knowledge_claims')->nullOnDelete();
            $table->string('status', 30)->default('active');
            $table->timestamp('invalidated_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['proyecto_id', 'status', 'epistemic_type']);
            $table->index(['subject_entity_id', 'predicate', 'status']);
            $table->index(['object_entity_id', 'status']);
            $table->index(['source_type', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_knowledge_claims');
    }
};
