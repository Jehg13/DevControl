<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nexus_memories', function (Blueprint $table) {
            if (! Schema::hasColumn('nexus_memories', 'memory_type')) {
                $table->string('memory_type', 30)->default('knowledge')->after('memory_key');
            }
            if (! Schema::hasColumn('nexus_memories', 'confidence')) {
                $table->unsignedTinyInteger('confidence')->default(50)->after('importance');
            }
            if (! Schema::hasColumn('nexus_memories', 'status')) {
                $table->string('status', 20)->default('active')->after('confidence');
            }
            if (! Schema::hasColumn('nexus_memories', 'last_confirmed_at')) {
                $table->timestamp('last_confirmed_at')->nullable()->after('last_used_at');
            }

            if (! Schema::getConnection()->getSchemaBuilder()->hasIndex('nexus_memories', ['usuario_id', 'memory_type', 'status'])) {
                $table->index(['usuario_id', 'memory_type', 'status']);
            }
            if (! Schema::getConnection()->getSchemaBuilder()->hasIndex('nexus_memories', ['proyecto_id', 'memory_type', 'status'])) {
                $table->index(['proyecto_id', 'memory_type', 'status']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('nexus_memories', function (Blueprint $table) {
            if (Schema::hasColumn('nexus_memories', 'usuario_id') && Schema::getConnection()->getSchemaBuilder()->hasIndex('nexus_memories', ['usuario_id', 'memory_type', 'status'])) {
                $table->dropIndex(['usuario_id', 'memory_type', 'status']);
            }
            if (Schema::hasColumn('nexus_memories', 'proyecto_id') && Schema::getConnection()->getSchemaBuilder()->hasIndex('nexus_memories', ['proyecto_id', 'memory_type', 'status'])) {
                $table->dropIndex(['proyecto_id', 'memory_type', 'status']);
            }
            foreach (['memory_type', 'confidence', 'status', 'last_confirmed_at'] as $column) {
                if (Schema::hasColumn('nexus_memories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
