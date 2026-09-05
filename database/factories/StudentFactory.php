<?php

namespace Database\Factories;

use App\Models\Family;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'last_name_two' => fake()->lastName(),
            'family_id' => Family::factory(),
            'id_number' => fake()->unique()->numerify('#-####-####'),
            'birth_date' => fake()->dateTimeBetween('-6 years', '-2 years')->format('Y-m-d'),
            'nationality' => 'Costarricense',
            'province' => 'San José',
            'canton' => 'Escazú',
            'address' => fake()->address(),
        ];
    }
}
