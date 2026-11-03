<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('board_entries', function (Blueprint $table) {
            $table->decimal('take_up_speed', 12, 4)->nullable();
            $table->decimal('rod_diameter_mm', 4, 1)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('board_entries', function (Blueprint $table) {
            $table->dropColumn(['take_up_speed', 'rod_diameter_mm']);
        });
    }
};
