<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nexus_memories', function (Blueprint $table): void {
            $table->timestamp('validated_at')->nullable()->after('last_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('nexus_memories', function (Blueprint $table): void {
            $table->dropColumn('validated_at');
        });
    }
};
