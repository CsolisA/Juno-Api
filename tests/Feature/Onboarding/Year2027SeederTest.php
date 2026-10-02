<?php

namespace Tests\Feature\Onboarding;

use App\Enums\AcademicYearStatus;
use App\Models\AcademicYear;
use App\Models\Group;
use Database\Seeders\ProductionSeeder;
use Database\Seeders\Year2027Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Year2027SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_one_group_per_level_and_is_idempotent(): void
    {
        $this->seed(ProductionSeeder::class);
        $this->seed(Year2027Seeder::class);

        $year = AcademicYear::where('year', 2027)->firstOrFail();
        $this->assertSame(AcademicYearStatus::Planeacion, $year->status);
        $this->assertSame(4, Group::where('academic_year_id', $year->id)->count());
        $this->assertSame(4, Group::where('academic_year_id', $year->id)->distinct()->count('grade_id'));
        $this->assertSame(1, AcademicYear::where('year', 2027)->count());
    }
}
