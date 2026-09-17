<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_recovery_checkpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nexus_run_id')->constrained('nexus_runs')->cascadeOnDelete();
            $table->string('operation_key', 160);
            $table->string('operation_type', 50);
            $table->json('scope');
            $table->json('snapshot')->nullable();
            $table->string('status', 30)->default('created');
            $table->string('rollback_status', 30)->default('not_requested');
            $table->json('rollback_result')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['nexus_run_id', 'status']);
            $table->unique(['nexus_run_id', 'operation_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_recovery_checkpoints');
    }
};
