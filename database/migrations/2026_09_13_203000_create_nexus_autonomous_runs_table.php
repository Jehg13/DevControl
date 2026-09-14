<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_autonomous_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nexus_run_id')->constrained('nexus_runs')->cascadeOnDelete();
            $table->foreignId('nexus_plan_id')->nullable()->constrained('nexus_plans')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->text('objective');
            $table->string('status', 35)->default('running');
            $table->json('plan')->nullable();
            $table->json('limits');
            $table->json('state')->nullable();
            $table->unsignedInteger('steps')->default(0);
            $table->unsignedInteger('retries')->default(0);
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'usuario_id']);
            $table->index(['proyecto_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_autonomous_runs');
    }
};
