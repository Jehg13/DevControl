<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nexus_runs', function (Blueprint $table) {
            $table->foreignId('nexus_conversation_id')
                ->nullable()
                ->after('usuario_id')
                ->constrained('nexus_conversations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('nexus_runs', function (Blueprint $table) {
            $table->dropForeign(['nexus_conversation_id']);
            $table->dropColumn('nexus_conversation_id');
        });
    }
};
