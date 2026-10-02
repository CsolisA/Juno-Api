<?php

namespace Database\Seeders;

use App\Enums\AcademicYearStatus;
use App\Enums\AdminUserType;
use App\Models\AcademicYear;
use App\Models\AdminUser;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Kinder;
use Illuminate\Database\Seeder;

class Year2027Seeder extends Seeder
{
    public const YEAR = 2027;

    /**
     * Next academic year in planning, with one catch-all group per level. The family onboarding
     * wizard enrolls every child into the group of the level the family picked; the director is
     * the temporary professor until real professors are assigned from the admin portal.
     * Idempotent, so it's safe to re-run. Requires ProductionSeeder's kinder, grades and director.
     */
    public function run(): void
    {
        $kinder = Kinder::where('name', 'KSorpresita')->first();
        $director = AdminUser::where('email', 'info@ksorpresita.com')
            ->where('type', AdminUserType::Director)
            ->first();

        if (! $kinder || ! $director) {
            $this->command?->error('Run ProductionSeeder first: the kinder or the director is missing.');

            return;
        }

        $year = AcademicYear::firstOrCreate(
            ['kinder_id' => $kinder->id, 'year' => self::YEAR],
            [
                'start_date' => self::YEAR.'-02-01',
                'end_date' => self::YEAR.'-12-15',
                'status' => AcademicYearStatus::Planeacion,
            ],
        );

        foreach (Grade::orderBy('order')->get() as $grade) {
            Group::firstOrCreate(
                ['grade_id' => $grade->id, 'academic_year_id' => $year->id],
                ['professor_id' => $director->id],
            );
        }

        $this->command?->info('Academic year '.self::YEAR.' and one group per level seeded.');
    }
}
