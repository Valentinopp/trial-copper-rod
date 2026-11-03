<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketing_sales_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('submission_token')->unique();
            $table->foreignId('product_id')->constrained('marketing_products')->restrictOnDelete();
            $table->string('customer_name');
            $table->string('product_name');
            $table->decimal('diameter_mm', 10, 3);
            $table->decimal('purity_percent', 7, 4);
            $table->decimal('quantity_kg', 16, 3);
            $table->date('sold_on');
            $table->date('ship_by')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_sales_orders');
    }
};
