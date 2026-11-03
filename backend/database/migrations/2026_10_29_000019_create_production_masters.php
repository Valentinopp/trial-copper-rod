<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'master_data_production_references';

    private const BOARD_FIELDS = ['take_up' => 'take-ups', 'cooling' => 'coilings', 'crystallizer' => 'crystallizers', 'rod_diameter_mm' => 'rod-specifications'];

    private const DETAIL_FIELDS = ['supervisor' => 'production-supervisors', 'event_at_break' => 'break-events', 'event_after_break' => 'break-conditions', 'category' => 'break-categories'];

    private const WEIGHTS = ['8' => 0.00045, '12.5' => 0.0011, '20' => 0.0028];

    public function up(): void
    {
        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->id();
            $table->string('section', 40);
            $table->string('code');
            $table->string('name');
            $table->string('servo', 1)->nullable();
            $table->decimal('diameter_mm', 4, 1)->nullable();
            $table->decimal('weight_ton_per_meter', 12, 10)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['section', 'code'], 'production_reference_code_unique');
            $table->unique(['section', 'name'], 'production_reference_name_unique');
            $table->unique(['section', 'diameter_mm'], 'production_reference_diameter_unique');
            $table->index(['section', 'is_active']);
        });
        Schema::table('board_entries', function (Blueprint $table) {
            foreach (array_keys(self::BOARD_FIELDS) as $field) {
                $table->foreignId($field.'_master_id')->nullable()->constrained(self::TABLE)->restrictOnDelete();
            }
            $table->decimal('rod_weight_ton_per_meter', 12, 10)->nullable();
            $table->boolean('uses_master_servo')->default(false);
        });
        Schema::table('copper_break_entries', function (Blueprint $table) {
            foreach (array_keys(self::DETAIL_FIELDS) as $field) {
                $table->foreignId($field.'_master_id')->nullable()->constrained(self::TABLE)->restrictOnDelete();
            }
        });

        foreach (['take-ups', 'coilings'] as $section) {
            foreach (['1XL', '1XR', '2YL', '2YR', '3XL', '3XR', '4YL', '4YR', '5XL', '5XR', '6YL', '6YR'] as $code) {
                $this->reference($section, $code);
            }
        }
        foreach (self::WEIGHTS as $diameter => $weight) {
            $this->reference('rod-specifications', (string) $diameter);
        }
        foreach ([
            'production-supervisors' => ['Danil', 'Tri', 'Samsul'],
            'break-events' => ['Tidak ada tindakan', 'Diputus', 'Panen 1x', 'Panen 2x', 'Problem putaran servo', 'Tembaga cair problem', 'Material refraktori bekas', 'Gagal saat akan panen ke-2', 'Fireclay', 'Visual', 'Proses', 'Black Out'],
            'break-conditions' => ['Cryst. Normal', 'Cryst. Normal (Lanjut)', 'Fireclay berlubang', 'Crystallizer', 'Diputus TKA', 'PLN Off'],
            'break-categories' => ['Tidak teridentifikasi', 'Visual merah', 'Visual crack', 'Efek bambu abnormal', 'Retak Kasar OK', 'Retak Tipis OK', 'Crystallizer', 'Fireclay', 'Proses', 'Visual'],
        ] as $section => $values) {
            foreach ($values as $value) {
                $this->reference($section, $value);
            }
        }

        // Preserve the text of historical transactions while linking each value to its master.
        foreach (['board_entries' => self::BOARD_FIELDS, 'copper_break_entries' => self::DETAIL_FIELDS] as $table => $fields) {
            DB::table($table)->orderBy('id')->chunkById(200, function ($rows) use ($table, $fields) {
                foreach ($rows as $row) {
                    $data = [];
                    foreach ($fields as $field => $section) {
                        $value = (string) ($row->$field ?? '');
                        if (trim($value) === '') {
                            continue;
                        }
                        if ($section === 'rod-specifications') {
                            $value = (string) (float) $value;
                        }
                        $data[$field.'_master_id'] = $this->reference($section, $value);
                        if ($section === 'rod-specifications') {
                            $data['rod_weight_ton_per_meter'] = self::WEIGHTS[$value] ?? null;
                        }
                    }
                    if ($data) {
                        DB::table($table)->where('id', $row->id)->update($data);
                    }
                }
            });
        }
    }

    private function reference(string $section, string $value): int
    {
        $equipment = in_array($section, ['take-ups', 'coilings', 'crystallizers'], true);
        $rod = $section === 'rod-specifications';
        $column = $equipment ? 'code' : ($rod ? 'diameter_mm' : 'name');
        $existing = DB::table(self::TABLE)->where('section', $section)->where($column, $value)->value('id');
        if ($existing) {
            return (int) $existing;
        }
        $servo = preg_match('/^(?:\d+)?([XY])(?:[LR])?$/i', trim($value), $match) ? strtoupper($match[1]) : null;
        $prefix = match ($section) {
            'production-supervisors' => 'SPV', 'break-events' => 'EVT',
            'break-conditions' => 'KON', 'break-categories' => 'KAT', default => 'REF',
        };

        return DB::table(self::TABLE)->insertGetId([
            'section' => $section,
            'code' => $equipment ? $value : ($rod ? 'CU-'.$value : $prefix.'-'.str_pad((string) (DB::table(self::TABLE)->where('section', $section)->count() + 1), 3, '0', STR_PAD_LEFT)),
            'name' => $rod ? 'Cu Rod '.$value.' mm' : $value,
            'servo' => in_array($section, ['take-ups', 'coilings'], true) ? $servo : null,
            'diameter_mm' => $rod ? (float) $value : null,
            'weight_ton_per_meter' => $rod ? (self::WEIGHTS[$value] ?? null) : null,
            // Unknown historical specifications need to be completed before use in a new transaction.
            'is_active' => ! ($rod && ! isset(self::WEIGHTS[$value])) && ! (in_array($section, ['take-ups', 'coilings'], true) && ! $servo),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        foreach (['copper_break_entries' => self::DETAIL_FIELDS, 'board_entries' => self::BOARD_FIELDS] as $table => $fields) {
            Schema::table($table, function (Blueprint $blueprint) use ($fields, $table) {
                foreach (array_keys($fields) as $field) {
                    $blueprint->dropConstrainedForeignId($field.'_master_id');
                }
                if ($table === 'board_entries') {
                    $blueprint->dropColumn(['rod_weight_ton_per_meter', 'uses_master_servo']);
                }
            });
        }
        Schema::dropIfExists(self::TABLE);
    }
};
