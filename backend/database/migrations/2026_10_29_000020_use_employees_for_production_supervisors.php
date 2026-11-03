<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('copper_break_entries', function (Blueprint $table) {
            $table->foreignId('supervisor_user_id')->nullable()->constrained('users')->restrictOnDelete();
        });
        $employees = DB::table('users')->join('master_data_job_titles as job', 'job.id', '=', 'users.job_title_id')
            ->join('master_data_divisions as division', 'division.id', '=', 'users.division_id')
            ->whereIn(DB::raw("LOWER(TRIM(REPLACE(job.name, '.', '')))"), ['spv', 'supervisor', 'spv produksi', 'supervisor produksi', 'production supervisor', 'supervisor production', 'spv production'])
            ->whereIn(DB::raw('LOWER(TRIM(division.name))'), ['produksi', 'production', 'produksi copper rod', 'copper rod'])
            ->get(['users.id', 'users.name'])->groupBy(fn ($row) => mb_strtolower(trim($row->name)));
        DB::table('copper_break_entries')->orderBy('id')->chunkById(200, function ($rows) use ($employees) {
            foreach ($rows as $row) {
                $matches = $employees->get(mb_strtolower(trim($row->supervisor)));
                // Ambiguous/unmatched names remain historical text; never create duplicate employee accounts.
                if ($matches?->count() === 1) {
                    DB::table('copper_break_entries')->where('id', $row->id)->update(['supervisor_user_id' => $matches->first()->id]);
                }
            }
        });
        Schema::table('copper_break_entries', fn (Blueprint $table) => $table->dropConstrainedForeignId('supervisor_master_id'));
        DB::table('master_data_production_references')->where('section', 'production-supervisors')->delete();
    }

    public function down(): void
    {
        Schema::table('copper_break_entries', function (Blueprint $table) {
            $table->foreignId('supervisor_master_id')->nullable()->constrained('master_data_production_references')->restrictOnDelete();
        });
        $names = DB::table('copper_break_entries')->where('supervisor', '!=', '')->distinct()->pluck('supervisor')
            ->merge(['Danil', 'Tri', 'Samsul'])->unique()->values();
        foreach ($names as $index => $name) {
            $id = DB::table('master_data_production_references')->insertGetId([
                'section' => 'production-supervisors', 'code' => 'SPV-'.str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'name' => $name, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('copper_break_entries')->where('supervisor', $name)->update(['supervisor_master_id' => $id]);
        }
        Schema::table('copper_break_entries', fn (Blueprint $table) => $table->dropConstrainedForeignId('supervisor_user_id'));
    }
};
