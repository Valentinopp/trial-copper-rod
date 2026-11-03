<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketing_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('diameter_mm', 10, 3);
            $table->decimal('purity_percent', 7, 4);
            $table->decimal('bom_output_kg', 16, 3);
            $table->timestamps();
        });
        Schema::create('marketing_bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('marketing_products')->cascadeOnDelete();
            $table->foreignId('material_id')->constrained('inventory_products')->restrictOnDelete();
            $table->decimal('quantity', 16, 3);
            $table->timestamps();
            $table->unique(['product_id', 'material_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_bom_items');
        Schema::dropIfExists('marketing_products');
    }
};
