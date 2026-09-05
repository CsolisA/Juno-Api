<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\TransportContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransportContact>
 */
class TransportContactFactory extends Factory
{
    protected $model = TransportContact::class;

    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'name' => fake()->name(),
            'id_number' => fake()->unique()->numerify('#-####-####'),
            'phone' => fake()->numerify('8###-####'),
            'display_order' => 0,
        ];
    }
}
