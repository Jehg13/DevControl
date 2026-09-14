<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nexus_memories', function (Blueprint $table) {
            $table->string('memory_type', 30)->default('knowledge')->after('memory_key');
            $table->unsignedTinyInteger('confidence')->default(50)->after('importance');
            $table->string('status', 20)->default('active')->after('confidence');
            $table->timestamp('last_confirmed_at')->nullable()->after('last_used_at');

            $table->index(['usuario_id', 'memory_type', 'status']);
            $table->index(['proyecto_id', 'memory_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('nexus_memories', function (Blueprint $table) {
            $table->dropIndex(['usuario_id', 'memory_type', 'status']);
            $table->dropIndex(['proyecto_id', 'memory_type', 'status']);
            $table->dropColumn(['memory_type', 'confidence', 'status', 'last_confirmed_at']);
        });
    }
};
