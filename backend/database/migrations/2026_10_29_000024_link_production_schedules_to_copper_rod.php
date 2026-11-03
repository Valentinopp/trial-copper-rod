<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_schedules', function (Blueprint $table) {
            $table->foreignId('rod_specification_id')->nullable()->constrained('master_data_production_references')->restrictOnDelete();
            $table->decimal('rod_diameter_mm', 4, 1)->nullable();
            $table->decimal('rod_weight_ton_per_meter', 12, 10)->nullable();
            $table->decimal('kg_per_unit', 18, 9)->nullable();
        });
        Schema::table('board_entries', function (Blueprint $table) {
            $table->foreignId('production_schedule_id')->nullable()->constrained('production_schedules')->restrictOnDelete();
            $table->decimal('reject_weight_kg', 20, 6)->default(0);
            $table->decimal('production_actual_kg', 20, 6)->nullable();
        });
        DB::statement('CREATE UNIQUE INDEX copper_rod_schedule_lot_cycle_unique ON board_entries (production_schedule_id, lot, crystallizer, upcasting_date, upcasting_time) WHERE production_schedule_id IS NOT NULL');
        $units = DB::table('master_data_units')->get()->keyBy('id');
        DB::table('production_schedules')->join('production_handoffs as handoff', 'handoff.id', '=', 'production_schedules.handoff_id')
            ->join('marketing_sales_orders as sale', 'sale.id', '=', 'handoff.sales_order_id')
            ->get(['production_schedules.id', 'sale.uom_id'])->each(function ($row) use ($units) {
                $unit = $units->get($row->uom_id);
                $base = $unit?->related_uom_id ? $units->get($unit->related_uom_id) : null;
                $factor = $unit && mb_strtoupper(trim($unit->code)) === 'KG' && ! $unit->related_uom_id ? 1
                    : ($base && mb_strtoupper(trim($base->code)) === 'KG' && ! $base->related_uom_id && (float) $unit->factor > 0 ? $unit->factor : null);
                DB::table('production_schedules')->where('id', $row->id)->update(['kg_per_unit' => $factor]);
            });
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS copper_rod_schedule_lot_cycle_unique');
        Schema::table('board_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('production_schedule_id');
            $table->dropColumn(['reject_weight_kg', 'production_actual_kg']);
        });
        Schema::table('production_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rod_specification_id');
            $table->dropColumn(['rod_diameter_mm', 'rod_weight_ton_per_meter', 'kg_per_unit']);
        });
    }
};
