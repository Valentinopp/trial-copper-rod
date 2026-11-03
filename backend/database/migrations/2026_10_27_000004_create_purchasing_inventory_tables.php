<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventory_products', function (Blueprint $table) {
            $table->id();
            $table->string('identity_key', 64)->unique();
            $table->string('name');
            $table->string('uom', 30);
            $table->timestamps();
        });
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
            $table->decimal('quantity', 16, 3);
            $table->date('required_on');
            $table->timestamps();
        });
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->unique()->constrained('purchase_requests')->restrictOnDelete();
            $table->date('ordered_on');
            $table->date('expected_on');
            $table->timestamps();
        });
        Schema::create('inventory_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('inventory_products')->restrictOnDelete();
            $table->uuid('receipt_token')->unique();
            $table->decimal('quantity', 16, 3);
            $table->date('received_on');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_receipts');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('purchase_requests');
        Schema::dropIfExists('inventory_products');
    }
};
