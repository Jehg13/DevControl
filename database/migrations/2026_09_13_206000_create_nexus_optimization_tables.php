<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_performance_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nexus_run_id')->constrained('nexus_runs')->cascadeOnDelete();
            $table->string('objective_hash', 64)->index();
            $table->foreignId('project_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->string('status', 35);
            $table->unsignedBigInteger('duration_ms')->default(0);
            $table->unsignedInteger('steps')->default(0);
            $table->unsignedInteger('tool_calls')->default(0);
            $table->unsignedInteger('failed_calls')->default(0);
            $table->unsignedInteger('retries')->default(0);
            $table->unsignedInteger('memory_items')->default(0);
            $table->json('redundant_tools')->nullable();
            $table->json('metrics')->nullable();
            $table->timestamps();
            $table->index(['project_id', 'status']);
        });

        Schema::create('nexus_optimization_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->string('proposal_key', 120)->unique();
            $table->string('category', 60);
            $table->text('observation');
            $table->text('proposal');
            $table->json('evidence')->nullable();
            $table->text('expected_impact')->nullable();
            $table->string('risk', 20)->default('medium');
            $table->string('status', 30)->default('draft');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('implemented_at')->nullable();
            $table->json('validation')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['status', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_optimization_proposals');
        Schema::dropIfExists('nexus_performance_metrics');
    }
};
