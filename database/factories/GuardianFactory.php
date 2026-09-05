<?php

namespace Database\Factories;

use App\Enums\GuardianRole;
use App\Enums\IdType;
use App\Models\Family;
use App\Models\Guardian;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guardian>
 */
class GuardianFactory extends Factory
{
    protected $model = Guardian::class;

    public function definition(): array
    {
        return [
            'family_id' => Family::factory(),
            'role' => fake()->randomElement(GuardianRole::cases()),
            'name' => fake()->firstName(),
            'last_name_one' => fake()->lastName(),
            'last_name_two' => fake()->lastName(),
            'nationality' => 'Costarricense',
            'id_type' => IdType::Cedula,
            'id_number' => fake()->unique()->numerify('#-####-####'),
            'birth_date' => fake()->dateTimeBetween('-50 years', '-20 years')->format('Y-m-d'),
            'marital_status' => fake()->randomElement(['soltero', 'casado', 'divorciado', 'viudo']),
            'education_level' => fake()->randomElement(['secundaria', 'universidad', 'posgrado']),
            'occupation' => fake()->jobTitle(),
            'workplace' => fake()->company(),
            'mobile_phone' => fake()->numerify('8###-####'),
            'lives_with_child' => true,
            'address' => fake()->address(),
            'email' => fake()->unique()->safeEmail(),
            'uses_whatsapp' => true,
            'uses_facebook' => false,
            'uses_instagram' => false,
            'uses_threads' => false,
            'is_primary_contact' => false,
            'status' => true,
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary_contact' => true]);
    }
}
