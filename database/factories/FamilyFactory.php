<?php

namespace Database\Factories;

use App\Models\Family;
use App\Models\Kinder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Family>
 */
class FamilyFactory extends Factory
{
    protected $model = Family::class;

    public function definition(): array
    {
        return [
            'kinder_id' => Kinder::factory(),
            'last_name_one' => fake()->lastName(),
            'last_name_two' => fake()->lastName(),
            'user' => fake()->unique()->userName(),
            'password' => 'password',
            'about_us' => fake()->sentence(),
            'referral_source' => 'other',
        ];
    }
}
