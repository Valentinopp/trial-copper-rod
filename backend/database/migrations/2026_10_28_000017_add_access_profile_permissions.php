<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('access_profiles', function (Blueprint $table) {
            $table->json('permissions')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('access_profiles', fn (Blueprint $table) => $table->dropColumn('permissions'));
    }
};