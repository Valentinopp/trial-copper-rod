<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('master_data_items', function (Blueprint $table) {
            $table->foreignId('rod_specification_id')->nullable()->constrained('master_data_production_references')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('master_data_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('rod_specification_id'));
    }
};
