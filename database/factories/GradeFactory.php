<?php

namespace Database\Factories;

use App\Models\Grade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Grade>
 */
class GradeFactory extends Factory
{
    protected $model = Grade::class;

    private static int $orderCounter = 0;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'order' => self::$orderCounter++,
            'is_final' => false,
        ];
    }

    public function final(): static
    {
        return $this->state(fn () => ['is_final' => true]);
    }
}
