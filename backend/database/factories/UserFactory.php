<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'name' => fake()->name(),
            'employee_number' => null,
            'job_title' => 'Operator',
            'department' => 'Produksi',
            'role' => UserRole::OperatorProduksi,
            'password' => 'TestPassword!2026',
            'is_active' => true,
        ];
    }
}
