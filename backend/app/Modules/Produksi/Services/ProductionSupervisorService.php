<?php

namespace App\Modules\Produksi\Services;

use App\Models\User;
use App\Modules\Produksi\Models\CopperBreakEntry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductionSupervisorService
{
    public function eligible(): Builder
    {
        return User::query()->select('users.*')
            ->join('master_data_job_titles as job', 'job.id', '=', 'users.job_title_id')
            ->join('master_data_divisions as division', 'division.id', '=', 'users.division_id')
            ->where('users.is_active', true)->where('job.is_active', true)->where('division.is_active', true)
            ->whereIn(DB::raw("LOWER(TRIM(REPLACE(job.name, '.', '')))"), [
                'spv', 'supervisor', 'spv produksi', 'supervisor produksi', 'production supervisor', 'supervisor production', 'spv production',
            ])
            ->whereIn(DB::raw('LOWER(TRIM(division.name))'), ['produksi', 'production', 'produksi copper rod', 'copper rod']);
    }

    public function options(?CopperBreakEntry $original = null): Collection
    {
        $options = $this->eligible()->orderBy('users.name')->orderBy('users.id')->get()
            ->map(fn (User $employee) => [
                'value' => (string) $employee->id,
                'label' => $employee->name.($employee->employee_number ? ' · '.$employee->employee_number : ' · @'.$employee->username),
            ]);
        if ($original && $original->supervisor !== '') {
            $value = $original->supervisor_user_id ? (string) $original->supervisor_user_id : 'legacy';
            if (! $options->contains('value', $value)) {
                $options->push(['value' => $value, 'label' => $original->supervisor.' (riwayat)']);
            }
        }

        return $options;
    }

    public function resolve(string $value, ?CopperBreakEntry $original = null): array
    {
        $originalValue = $original?->supervisor_user_id ? (string) $original->supervisor_user_id : 'legacy';
        if ($original && $original->supervisor !== '' && $value === $originalValue) {
            return ['supervisor_user_id' => $original->supervisor_user_id, 'supervisor' => $original->supervisor];
        }
        $employee = ctype_digit($value) ? $this->eligible()->where('users.id', $value)->lockForUpdate()->first() : null;
        if (! $employee) {
            throw ValidationException::withMessages(['supervisor_user_id' => 'Pilih karyawan aktif dengan jabatan SPV/Supervisor di divisi Produksi.']);
        }

        return ['supervisor_user_id' => $employee->id, 'supervisor' => $employee->name];
    }
}
