<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('output_formula_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->decimal('factor_a', 16, 10);
            $table->decimal('factor_b', 16, 10);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('output_formula_settings');
    }
};
