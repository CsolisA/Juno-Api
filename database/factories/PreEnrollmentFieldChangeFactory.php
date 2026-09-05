<?php

namespace Database\Factories;

use App\Enums\ActorType;
use App\Models\PreEnrollmentFieldChange;
use App\Models\PreEnrollmentForm;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreEnrollmentFieldChange>
 */
class PreEnrollmentFieldChangeFactory extends Factory
{
    protected $model = PreEnrollmentFieldChange::class;

    public function definition(): array
    {
        return [
            'form_id' => PreEnrollmentForm::factory(),
            'family_draft_id' => null,
            'field_path' => fake()->randomElement(['guardians.mother.mobilePhone', 'student.address', 'student.bloodType']),
            'old_value' => fake()->word(),
            'new_value' => fake()->word(),
            'actor_type' => ActorType::Family,
            'actor_id' => 1,
        ];
    }
}
