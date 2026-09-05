<?php

namespace Database\Factories;

use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Schedule;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'group_id' => Group::factory(),
            // Kept consistent with `group_id`'s grade, since campaign creation reads grade_id
            // directly off the enrollment rather than joining through the group.
            'grade_id' => fn (array $attributes) => Group::find($attributes['group_id'])?->grade_id,
            'schedule_id' => Schedule::factory(),
            'status' => EnrollmentStatus::Active,
            'source' => EnrollmentSource::Manual,
            'enrollment_fee_amount' => 300.00,
            'monthly_fee_amount' => 250.00,
            'uniform_size' => '4',
            'uniform_qty_shirt' => 2,
            'uniform_qty_short' => 2,
        ];
    }
}
