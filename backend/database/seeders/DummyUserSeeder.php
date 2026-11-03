<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DummyUserSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->table(['Role', 'Username', 'Password dummy'], $this->sync());
    }

    public function sync(bool $resetPasswords = false): array
    {
        if (!app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Akun dummy sederhana hanya boleh dibuat pada environment local atau testing.');
        }
        $accounts = [
            ['produksi', 'DUMMY-001', 'Operator Produksi Dummy', 'Operator', 'Produksi', UserRole::OperatorProduksi],
            ['asman', 'DUMMY-002', 'Asisten Manager Dummy', 'Asisten Manager', 'Manajemen', UserRole::AsistenManager],
            ['manager', 'DUMMY-003', 'Manager Dummy', 'Manager', 'Manajemen', UserRole::Manager],
            ['ppic', 'DUMMY-004', 'PPIC Dummy', 'Staff', 'PPIC', UserRole::Ppic],
            ['marketing', 'DUMMY-005', 'Marketing Dummy', 'Staff', 'Marketing', UserRole::Marketing],
            ['purchasing', 'DUMMY-006', 'Purchasing Dummy', 'Staff', 'Purchasing', UserRole::Purchasing],
            ['it', 'DUMMY-007', 'IT Super Admin Dummy', 'Super Admin', 'IT', UserRole::SuperAdmin],
        ];
        return DB::transaction(function () use ($accounts, $resetPasswords) {
            $credentials = [];
            foreach ($accounts as [$username, $number, $name, $title, $department, $role]) {
                $user = User::where('employee_number', $number)->lockForUpdate()->first();
                $collision = User::where('username', $username)->first();
                if ($collision && (! $user || $collision->id !== $user->id)) {
                    throw new \RuntimeException('Username '.$username.' sudah digunakan akun lain; akun tersebut tidak diubah.');
                }
                if ($user && ! $resetPasswords) {
                    $credentials[] = [$user->role->label(), $user->username, 'Sudah ada; password tidak diubah'];
                    continue;
                }
                $user ??= new User;
                $user->fill([
                    'username' => $username, 'employee_number' => $number, 'name' => $name,
                    'job_title' => $title, 'department' => $department, 'role' => $role,
                    'password' => $username, 'is_active' => true,
                ])->save();
                $credentials[] = [$role->label(), $username, $username];
            }
            return $credentials;
        });
    }
}
