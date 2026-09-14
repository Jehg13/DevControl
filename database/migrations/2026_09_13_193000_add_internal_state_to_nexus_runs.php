<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nexus_runs', function (Blueprint $table) {
            $table->json('internal_state')->nullable()->after('context');
        });
    }

    public function down(): void
    {
        Schema::table('nexus_runs', function (Blueprint $table) {
            $table->dropColumn('internal_state');
        });
    }
};
