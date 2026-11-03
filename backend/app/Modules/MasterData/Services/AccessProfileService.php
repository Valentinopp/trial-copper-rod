<?php

namespace App\Modules\MasterData\Services;

use App\Modules\MasterData\Models\AccessProfile;
use App\Modules\MasterData\Models\Division;
use App\Modules\MasterData\Models\JobTitle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccessProfileService
{
    public function save(array $data, ?int $id = null): AccessProfile
    {
        Gate::authorize('access-master-data');
        $valid = Validator::make($data, [
            'job_title_id' => ['required', 'integer', Rule::exists('master_data_job_titles', 'id')],
            'division_id' => ['required', 'integer', Rule::exists('master_data_divisions', 'id')],
            'is_active' => ['required', 'boolean'],
            'configured_modules' => ['sometimes', 'array'],
            'configured_modules.*' => ['string', 'distinct', Rule::in(array_keys(config('access_permissions')))],
            'permissions' => ['sometimes', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(\App\Services\AccessPermissionService::keys())],
        ], [
            'required' => ':attribute wajib diisi.',
            'exists' => ':attribute tidak ditemukan.',
            'integer' => ':attribute tidak valid.',
            'boolean' => ':attribute tidak valid.',
        ], ['job_title_id' => 'Jabatan', 'division_id' => 'Divisi', 'is_active' => 'Status'])->validate();

        if (isset($valid['permissions'])) {
            $valid['permissions'] = \App\Services\AccessPermissionService::normalize($valid['permissions']);
        }

        try {
            return DB::transaction(function () use ($valid, $id) {
                $profile = $id ? AccessProfile::lockForUpdate()->findOrFail($id) : new AccessProfile;
                foreach (['job_title_id' => JobTitle::class, 'division_id' => Division::class] as $key => $model) {
                    $master = $model::lockForUpdate()->findOrFail($valid[$key]);
                    if (! $master->is_active && (int) $profile->$key !== (int) $master->id) {
                        throw ValidationException::withMessages([$key => 'Pilih '.($key === 'job_title_id' ? 'jabatan' : 'divisi').' yang aktif.']);
                    }
                }
                $duplicate = AccessProfile::where('job_title_id', $valid['job_title_id'])
                    ->where('division_id', $valid['division_id']);
                if ($id) {
                    $duplicate->where('id', '!=', $id);
                }
                if ($duplicate->exists()) {
                    throw $this->duplicateException();
                }
                $valid['configured_modules'] = $valid['configured_modules'] ?? $profile->configured_modules ?? [];
                foreach ($valid['permissions'] ?? [] as $permission) {
                    $valid['configured_modules'][] = \App\Services\AccessPermissionService::moduleFor($permission);
                }
                $valid['configured_modules'] = array_values(array_unique($valid['configured_modules']));
                $profile->fill($valid)->save();

                return $profile;
            });
        } catch (UniqueConstraintViolationException $exception) {
            // The database constraint also protects concurrent requests.
            throw $this->duplicateException();
        }
    }

    private function duplicateException(): ValidationException
    {
        return ValidationException::withMessages([
            'division_id' => 'Hak akses untuk kombinasi jabatan dan divisi ini sudah terdaftar. Ubah pengaturan yang sudah ada.',
        ]);
    }
}