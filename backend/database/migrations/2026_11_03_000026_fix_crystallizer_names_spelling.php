<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('master_data_production_references')
            ->where('section', 'crystallizers')
            ->where('name', 'like', 'Crystalizer %')
            ->get();

        foreach ($rows as $row) {
            $newName = preg_replace('/^Crystalizer\b/i', 'Crystallizer', $row->name);
            $exists = DB::table('master_data_production_references')
                ->where('section', 'crystallizers')
                ->where('name', $newName)
                ->where('id', '<>', $row->id)
                ->exists();

            if (! $exists) {
                DB::table('master_data_production_references')
                    ->where('id', $row->id)
                    ->update(['name' => $newName]);
            }
        }
    }

    public function down(): void
    {
        // No-op
    }
};
