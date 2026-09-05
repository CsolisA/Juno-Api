<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\Schedule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ScheduleGradeSeeder extends Seeder
{
    /**
     * Seed every schedule as active for every grade. This is the explicit steady state a
     * director toggles off from; "empty pivot = all allowed" is a fallback for incomplete
     * data, not the intended starting point.
     */
    public function run(): void
    {
        $scheduleIds = Schedule::pluck('id');
        $gradeIds = Grade::pluck('id');

        foreach ($scheduleIds as $scheduleId) {
            foreach ($gradeIds as $gradeId) {
                DB::table('schedule_grade')->updateOrInsert(
                    ['schedule_id' => $scheduleId, 'grade_id' => $gradeId],
                    ['is_active' => true],
                );
            }
        }
    }
}
