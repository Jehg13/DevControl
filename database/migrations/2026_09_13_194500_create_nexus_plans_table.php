<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nexus_run_id')->unique()->constrained('nexus_runs')->cascadeOnDelete();
            $table->text('objective');
            $table->json('subtasks');
            $table->string('status', 30)->default('draft');
            $table->unsignedTinyInteger('priority')->default(50);
            $table->text('expected_result')->nullable();
            $table->text('completion_criteria')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_plans');
    }
};
