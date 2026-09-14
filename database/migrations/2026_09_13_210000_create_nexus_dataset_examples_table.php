<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_dataset_examples', function (Blueprint $table) {
            $table->id();
            $table->string('example_hash', 64)->unique();
            $table->string('version', 40)->default('1.0');
            $table->string('split', 20)->default('training');
            $table->json('payload');
            $table->json('labels')->nullable();
            $table->unsignedTinyInteger('quality_score')->default(0);
            $table->string('status', 30)->default('active');
            $table->string('source_type', 80);
            $table->string('source_id', 190)->nullable();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->timestamp('invalidated_at')->nullable();
            $table->timestamps();

            $table->index(['split', 'status']);
            $table->index(['source_type', 'source_id']);
            $table->index(['proyecto_id', 'quality_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_dataset_examples');
    }
};
