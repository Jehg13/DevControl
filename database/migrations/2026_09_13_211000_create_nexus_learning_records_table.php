<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_learning_records', function (Blueprint $table) {
            $table->id();
            $table->string('learning_key', 190);
            $table->string('learning_type', 40);
            $table->string('status', 30)->default('observed');
            $table->string('pattern', 120);
            $table->json('observation');
            $table->json('evidence');
            $table->json('proposed_update')->nullable();
            $table->json('applied_update')->nullable();
            $table->json('rollback_snapshot')->nullable();
            $table->unsignedTinyInteger('confidence')->default(0);
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->string('source_type', 80);
            $table->string('source_id', 190)->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->timestamps();

            $table->unique(['learning_key', 'status']);
            $table->index(['pattern', 'status']);
            $table->index(['source_type', 'source_id']);
            $table->index(['proyecto_id', 'learning_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_learning_records');
    }
};
