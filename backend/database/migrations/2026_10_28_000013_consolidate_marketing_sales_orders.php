<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('marketing_sales')->whereIn('submission_token', DB::table('marketing_sales_orders')->select('submission_token'))->exists()) {
            throw new RuntimeException('Token penjualan bertabrakan. Periksa transaksi sebelum penggabungan.');
        }
        $hasProductSnapshot = Schema::hasColumn('marketing_sales_orders', 'product_snapshot');
        Schema::table('marketing_sales_orders', function (Blueprint $table) use ($hasProductSnapshot) {
            $table->dropForeign(['product_id']);
            $table->unsignedBigInteger('product_id')->nullable()->change();
            $table->foreignId('customer_id')->nullable()->constrained('master_data_customers')->restrictOnDelete();
            $table->foreignId('uom_id')->nullable()->constrained('master_data_units')->restrictOnDelete();
            $table->json('customer_snapshot')->nullable();
            if (! $hasProductSnapshot) {
                $table->json('product_snapshot')->nullable();
            }
            $table->json('unit_snapshot')->nullable();
        });
        $kg = DB::table('master_data_units')->where('code', 'KG')->first();
        DB::table('marketing_sales_orders')->orderBy('id')->chunkById(100, function ($orders) use ($kg) {
            foreach ($orders as $order) {
                DB::table('marketing_sales_orders')->where('id', $order->id)->update([
                    'product_id' => null,
                    'uom_id' => $kg?->id,
                    'customer_snapshot' => json_encode(['code' => null, 'name' => $order->customer_name], JSON_THROW_ON_ERROR),
                    'product_snapshot' => json_encode(array_replace(['code' => null, 'name' => $order->product_name,
                        'diameter_mm' => $order->diameter_mm, 'purity_percent' => $order->purity_percent],
                        json_decode($order->product_snapshot ?? 'null', true) ?? []), JSON_THROW_ON_ERROR),
                    'unit_snapshot' => json_encode(['code' => 'KG', 'name' => 'Kilogram'], JSON_THROW_ON_ERROR),
                ]);
            }
        });
        Schema::table('marketing_sales_orders', function (Blueprint $table) {
            $table->foreign('product_id')->references('id')->on('master_data_items')->restrictOnDelete();
            $table->renameColumn('quantity_kg', 'quantity');
            $table->dropColumn(['customer_name', 'product_name', 'diameter_mm', 'purity_percent']);
        });
        Schema::table('marketing_sales_orders', fn (Blueprint $table) => $table->decimal('quantity', 20, 6)->change());
        DB::table('marketing_sales')->orderBy('id')->chunkById(100, function ($sales) {
            foreach ($sales as $sale) {
                $data = (array) $sale;
                unset($data['id']);
                DB::table('marketing_sales_orders')->insert($data);
            }
        });
        Schema::drop('marketing_sales');
    }

    public function down(): void
    {
        throw new RuntimeException('Penggabungan penjualan tidak dapat di-rollback otomatis. Pulihkan backup untuk mengembalikan skema sebelumnya.');
    }
};
