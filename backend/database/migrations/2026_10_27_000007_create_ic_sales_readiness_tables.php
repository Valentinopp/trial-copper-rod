<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ic_sale_material_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->constrained('marketing_sales_orders')->restrictOnDelete();
            $table->foreignId('material_id')->constrained('inventory_products')->restrictOnDelete();
            $table->string('material_name');
            $table->string('uom', 30);
            $table->decimal('quantity', 16, 3);
            $table->timestamps();
            $table->unique(['sales_order_id', 'material_id']);
        });
        Schema::create('production_handoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sales_order_id')->unique()->constrained('marketing_sales_orders')->restrictOnDelete();
            $table->timestamp('handed_over_at');
            $table->timestamps();
        });
        Schema::create('production_material_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('handoff_id')->constrained('production_handoffs')->restrictOnDelete();
            $table->foreignId('material_id')->constrained('inventory_products')->restrictOnDelete();
            $table->decimal('quantity', 16, 3);
            $table->timestamps();
            $table->unique(['handoff_id', 'material_id']);
        });
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->foreignId('sales_order_id')->nullable()->constrained('marketing_sales_orders')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', fn (Blueprint $table) => $table->dropConstrainedForeignId('sales_order_id'));
        Schema::dropIfExists('production_material_reservations');
        Schema::dropIfExists('production_handoffs');
        Schema::dropIfExists('ic_sale_material_requirements');
    }
};
