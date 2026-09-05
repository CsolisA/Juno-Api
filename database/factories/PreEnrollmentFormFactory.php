<?php

namespace Database\Factories;

use App\Enums\PreEnrollmentFormStatus;
use App\Models\Family;
use App\Models\Grade;
use App\Models\PreEnrollmentCampaign;
use App\Models\PreEnrollmentForm;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreEnrollmentForm>
 */
class PreEnrollmentFormFactory extends Factory
{
    protected $model = PreEnrollmentForm::class;

    public function definition(): array
    {
        return [
            'campaign_id' => PreEnrollmentCampaign::factory(),
            'student_id' => Student::factory(),
            'family_id' => Family::factory(),
            'current_grade_id' => Grade::factory(),
            'status' => PreEnrollmentFormStatus::Pending,
            'is_excluded' => false,
            'draft_payload' => [],
        ];
    }
}
