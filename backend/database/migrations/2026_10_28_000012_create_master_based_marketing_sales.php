<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('submission_token')->unique();
            $table->foreignId('customer_id')->constrained('master_data_customers')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('master_data_items')->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('master_data_units')->restrictOnDelete();
            $table->json('customer_snapshot');
            $table->json('product_snapshot');
            $table->json('unit_snapshot');
            $table->decimal('quantity', 20, 6);
            $table->date('sold_on');
            $table->date('ship_by')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_sales');
    }
};
