<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_infrastructures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 190);
            $table->string('provider', 100)->nullable();
            $table->json('server')->nullable();
            $table->json('operating_system')->nullable();
            $table->json('resources')->nullable();
            $table->json('services')->nullable();
            $table->json('applications')->nullable();
            $table->json('databases')->nullable();
            $table->json('domains')->nullable();
            $table->json('ssl')->nullable();
            $table->json('health')->nullable();
            $table->timestamp('last_check')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['proyecto_id', 'last_check']);
        });

        Schema::create('nexus_infrastructure_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('infrastructure_id')->constrained('nexus_infrastructures')->cascadeOnDelete();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->string('type', 80);
            $table->string('severity', 20);
            $table->string('title', 190);
            $table->text('description');
            $table->json('evidence')->nullable();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['infrastructure_id', 'severity', 'resolved_at'], 'nexus_infra_events_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_infrastructure_events');
        Schema::dropIfExists('nexus_infrastructures');
    }
};
