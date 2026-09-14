<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 40)->default('application');
            $table->text('message');
            $table->string('status', 30)->default('running');
            $table->unsignedInteger('steps')->default(0);
            $table->json('context')->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('nexus_tool_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nexus_run_id')->constrained('nexus_runs')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('tool_name', 120);
            $table->json('arguments')->nullable();
            $table->string('status', 30)->default('pending');
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['nexus_run_id', 'sequence']);
            $table->index(['tool_name', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_tool_calls');
        Schema::dropIfExists('nexus_runs');
    }
};
