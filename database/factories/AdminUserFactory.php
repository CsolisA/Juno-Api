<?php

namespace Database\Factories;

use App\Enums\AdminUserType;
use App\Models\AdminUser;
use App\Models\Kinder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminUser>
 */
class AdminUserFactory extends Factory
{
    protected $model = AdminUser::class;

    public function definition(): array
    {
        return [
            'kinder_id' => Kinder::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'type' => AdminUserType::Director,
            'password' => 'password',
            'emergency_phone' => fake()->phoneNumber(),
            'emergency_name' => fake()->name(),
            'birth_date' => fake()->date(),
            'hire_date' => fake()->date(),
            'status' => true,
            'address' => fake()->address(),
        ];
    }
}
