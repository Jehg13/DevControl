<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_permission_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nexus_run_id')->nullable()->constrained('nexus_runs')->nullOnDelete();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->string('tool_name', 120);
            $table->string('action', 120);
            $table->string('risk_level', 20);
            $table->json('requested_permissions');
            $table->json('granted_permissions')->nullable();
            $table->string('status', 40);
            $table->json('result')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['tool_name', 'status']);
            $table->index(['proyecto_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_permission_audits');
    }
};
