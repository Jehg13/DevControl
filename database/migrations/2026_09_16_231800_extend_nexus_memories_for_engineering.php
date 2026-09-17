<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nexus_memories', function (Blueprint $table): void {
            if (! Schema::hasColumn('nexus_memories', 'memory_type')) {
                $table->string('memory_type', 30)->default('experience')->after('source');
            }
            if (! Schema::hasColumn('nexus_memories', 'confidence')) {
                $table->unsignedTinyInteger('confidence')->default(50)->after('importance');
            }
            if (! Schema::hasColumn('nexus_memories', 'evidence')) {
                $table->json('evidence')->nullable()->after('metadata');
            }
            if (! Schema::hasColumn('nexus_memories', 'relationships')) {
                $table->json('relationships')->nullable()->after('evidence');
            }
            if (! Schema::hasColumn('nexus_memories', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('last_used_at');
            }
            if (! Schema::hasColumn('nexus_memories', 'access_scope')) {
                $table->string('access_scope', 20)->default('project')->after('expires_at');
            }

            if (! Schema::getConnection()->getSchemaBuilder()->hasIndex('nexus_memories', ['proyecto_id', 'memory_type', 'expires_at'])) {
                $table->index(['proyecto_id', 'memory_type', 'expires_at']);
            }
            if (! Schema::getConnection()->getSchemaBuilder()->hasIndex('nexus_memories', ['usuario_id', 'access_scope', 'expires_at'])) {
                $table->index(['usuario_id', 'access_scope', 'expires_at']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('nexus_memories', function (Blueprint $table): void {
            if (Schema::getConnection()->getSchemaBuilder()->hasIndex('nexus_memories', ['proyecto_id', 'memory_type', 'expires_at'])) {
                $table->dropIndex(['proyecto_id', 'memory_type', 'expires_at']);
            }
            if (Schema::getConnection()->getSchemaBuilder()->hasIndex('nexus_memories', ['usuario_id', 'access_scope', 'expires_at'])) {
                $table->dropIndex(['usuario_id', 'access_scope', 'expires_at']);
            }
            foreach (['memory_type', 'confidence', 'evidence', 'relationships', 'expires_at', 'access_scope'] as $column) {
                if (Schema::hasColumn('nexus_memories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
