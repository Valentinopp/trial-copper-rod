<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS production_schedule_active_day_unique');
    }

    public function down(): void
    {
        $duplicates = DB::table('production_schedules')->where('status', 'scheduled')
            ->select('handoff_id', 'planned_on')->groupBy('handoff_id', 'planned_on')->havingRaw('COUNT(*) > 1')->exists();
        if ($duplicates) {
            throw new RuntimeException('Rollback tidak dapat dilakukan karena pesanan memiliki beberapa jadwal aktif pada tanggal yang sama. Data jadwal tetap dipertahankan.');
        }
        DB::statement("CREATE UNIQUE INDEX production_schedule_active_day_unique ON production_schedules (handoff_id, planned_on) WHERE status = 'scheduled'");
    }
};
