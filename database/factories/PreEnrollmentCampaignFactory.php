<?php

namespace Database\Factories;

use App\Enums\PreEnrollmentCampaignStatus;
use App\Models\AcademicYear;
use App\Models\PreEnrollmentCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreEnrollmentCampaign>
 */
class PreEnrollmentCampaignFactory extends Factory
{
    protected $model = PreEnrollmentCampaign::class;

    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'name' => fake()->sentence(3),
            'status' => PreEnrollmentCampaignStatus::Draft,
            'enforce_due_date' => false,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => PreEnrollmentCampaignStatus::Closed]);
    }
}
