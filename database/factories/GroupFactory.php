<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\AdminUser;
use App\Models\Grade;
use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    public function definition(): array
    {
        return [
            'grade_id' => Grade::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'professor_id' => AdminUser::factory(),
        ];
    }
}
