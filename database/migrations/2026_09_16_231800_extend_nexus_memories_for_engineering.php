<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nexus_memories', function (Blueprint $table): void {
            $table->string('memory_type', 30)->default('experience')->after('source');
            $table->unsignedTinyInteger('confidence')->default(50)->after('importance');
            $table->json('evidence')->nullable()->after('metadata');
            $table->json('relationships')->nullable()->after('evidence');
            $table->timestamp('expires_at')->nullable()->after('last_used_at');
            $table->string('access_scope', 20)->default('project')->after('expires_at');
            $table->index(['proyecto_id', 'memory_type', 'expires_at']);
            $table->index(['usuario_id', 'access_scope', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::table('nexus_memories', function (Blueprint $table): void {
            $table->dropIndex(['nexus_memories_proyecto_id_memory_type_expires_at_index']);
            $table->dropIndex(['nexus_memories_usuario_id_access_scope_expires_at_index']);
            $table->dropColumn([
                'memory_type',
                'confidence',
                'evidence',
                'relationships',
                'expires_at',
                'access_scope',
            ]);
        });
    }
};
