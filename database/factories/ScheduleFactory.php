<?php

namespace Database\Factories;

use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('SCHED???')),
            'name' => fake()->word(),
            'description' => null,
            'start_time' => '07:00:00',
            'end_time' => '12:40:00',
            'days_per_week' => 5,
            'display_order' => 0,
            'is_active' => true,
        ];
    }
}
