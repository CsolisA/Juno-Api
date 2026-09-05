<?php

namespace Database\Factories;

use App\Models\Kinder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kinder>
 */
class KinderFactory extends Factory
{
    protected $model = Kinder::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'main_color' => fake()->hexColor(),
            'second_color' => fake()->hexColor(),
            'font_name' => 'FREDOKA',
        ];
    }
}
