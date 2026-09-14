<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nexus_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_key', 100)->index();
            $table->string('title', 190)->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });

        Schema::create('nexus_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nexus_conversation_id')->constrained('nexus_conversations')->cascadeOnDelete();
            $table->string('role', 20);
            $table->longText('content');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['nexus_conversation_id', 'created_at']);
        });

        Schema::create('nexus_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('nexus_conversation_id')->nullable()->constrained('nexus_conversations')->nullOnDelete();
            $table->unsignedBigInteger('proyecto_id')->nullable();
            $table->string('memory_key', 120);
            $table->text('content');
            $table->string('source', 40)->default('conversation');
            $table->unsignedTinyInteger('importance')->default(50);
            $table->json('metadata')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['usuario_id', 'memory_key']);
            $table->index(['proyecto_id', 'importance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nexus_memories');
        Schema::dropIfExists('nexus_messages');
        Schema::dropIfExists('nexus_conversations');
    }
};
