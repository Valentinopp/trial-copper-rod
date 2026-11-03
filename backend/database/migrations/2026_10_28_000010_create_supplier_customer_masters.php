<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['master_data_suppliers', 'master_data_customers'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->unique();
                $table->string('name');
                $table->string('contact_name')->nullable();
                $table->string('phone', 100)->nullable();
                $table->string('email')->nullable();
                $table->text('address')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        Schema::create('master_data_supplier_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained('master_data_suppliers')->restrictOnDelete();
            $table->foreignId('item_id')->constrained('master_data_items')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['supplier_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_data_supplier_items');
        Schema::dropIfExists('master_data_customers');
        Schema::dropIfExists('master_data_suppliers');
    }
};
