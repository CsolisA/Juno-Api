<?php

namespace Database\Factories;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\Kinder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicYear>
 */
class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    public function definition(): array
    {
        $year = fake()->numberBetween(2024, 2030);

        return [
            'kinder_id' => Kinder::factory(),
            'year' => $year,
            'start_date' => "{$year}-02-01",
            'end_date' => "{$year}-12-15",
        ];
    }

    /**
     * Make this the "current" year: activo (the actual source of truth AcademicYear::current()
     * reads) with dates that also happen to span today, so anything still displaying start/end
     * dates sees something sensible.
     */
    public function current(): static
    {
        return $this->state(fn () => [
            'year' => now()->year,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonths(6),
            'status' => AcademicYearStatus::Activo,
        ]);
    }

    public function planeacion(): static
    {
        return $this->state(fn () => ['status' => AcademicYearStatus::Planeacion]);
    }

    public function activo(): static
    {
        return $this->state(fn () => ['status' => AcademicYearStatus::Activo]);
    }

    public function cerrado(): static
    {
        return $this->state(fn () => ['status' => AcademicYearStatus::Cerrado]);
    }
}
