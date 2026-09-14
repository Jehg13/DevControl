<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_proactive_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proyecto_id')->nullable()->constrained('proyectos')->nullOnDelete();
            $table->string('source', 40);
            $table->string('group_key', 128);
            $table->string('fingerprint', 64)->unique();
            $table->string('priority', 20)->default('Media');
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('occurrences')->default(1);
            $table->dateTime('first_seen');
            $table->dateTime('last_seen');
            $table->dateTime('last_notified_at')->nullable();
            $table->dateTime('silenced_until')->nullable();
            $table->json('evidence')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['proyecto_id', 'status']);
            $table->index(['group_key', 'last_seen']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_proactive_alerts');
    }
};
