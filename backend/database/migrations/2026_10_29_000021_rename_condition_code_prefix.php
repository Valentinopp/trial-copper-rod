<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->renamePrefix('KON-', 'KDS-');
    }

    public function down(): void
    {
        $this->renamePrefix('KDS-', 'KON-');
    }

    private function renamePrefix(string $from, string $to): void
    {
        DB::transaction(function () use ($from, $to) {
            $conditions = DB::table('master_data_production_references')
                ->where('section', 'break-conditions')->where('code', 'like', $from.'%')
                ->orderBy('id')->lockForUpdate()->get(['id', 'code']);

            foreach ($conditions as $condition) {
                $code = $to.substr($condition->code, strlen($from));
                if (DB::table('master_data_production_references')->where('section', 'break-conditions')->where('code', $code)->exists()) {
                    throw new RuntimeException('Kode kondisi '.$code.' sudah digunakan. Perubahan dibatalkan untuk mencegah duplikasi.');
                }
                DB::table('master_data_production_references')->where('id', $condition->id)
                    ->update(['code' => $code, 'updated_at' => now()]);
            }
        });
    }
};
