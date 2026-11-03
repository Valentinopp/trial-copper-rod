<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_data_categories', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('master_data_categories')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('master_data_units', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->foreignId('related_uom_id')->nullable()->constrained('master_data_units')->restrictOnDelete();
            $table->decimal('factor', 24, 9)->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('master_data_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 60)->unique();
            $table->string('name');
            $table->foreignId('category_id')->constrained('master_data_categories')->restrictOnDelete();
            $table->foreignId('uom_id')->constrained('master_data_units')->restrictOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $now = now();
        $timestamps = ['created_at' => $now, 'updated_at' => $now];
        foreach (['BRG' => 'Barang', 'MAT' => 'Material', 'SP' => 'Sparepart'] as $code => $name) {
            DB::table('master_data_categories')->insert($timestamps + ['code' => $code, 'name' => $name]);
        }
        foreach (['KG' => 'Kilogram', 'PCS' => 'Piece', 'M' => 'Meter', 'L' => 'Liter'] as $code => $name) {
            DB::table('master_data_units')->insert($timestamps + ['code' => $code, 'name' => $name, 'factor' => 1]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('master_data_items');
        DB::table('master_data_units')->update(['related_uom_id' => null]);
        DB::table('master_data_categories')->update(['parent_id' => null]);
        Schema::dropIfExists('master_data_units');
        Schema::dropIfExists('master_data_categories');
    }
};
