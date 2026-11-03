<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_data_boms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->foreignId('product_id')->constrained('master_data_items')->restrictOnDelete();
            $table->decimal('output_quantity', 20, 6);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('master_data_bom_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained('master_data_boms')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('master_data_items')->restrictOnDelete();
            $table->decimal('quantity', 20, 6);
            $table->timestamps();
            $table->unique(['bom_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_data_bom_lines');
        Schema::dropIfExists('master_data_boms');
    }
};
