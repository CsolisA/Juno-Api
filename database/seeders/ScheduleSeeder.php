<?php

namespace Database\Seeders;

use App\Models\Schedule;
use Illuminate\Database\Seeder;

class ScheduleSeeder extends Seeder
{
    /**
     * Seed the three canonical schedule options. Idempotent, so it's safe to re-run against a
     * DB that already has the `schedules` table (e.g. one that wasn't freshly migrated).
     */
    public function run(): void
    {
        $schedules = [
            [
                'code' => 'PRESENCIAL',
                'name' => 'Presencial',
                'description' => '5 días a la semana',
                'start_time' => '07:00:00',
                'end_time' => '12:40:00',
                'days_per_week' => 5,
                'display_order' => 1,
            ],
            [
                'code' => 'ALTERNO',
                'name' => 'Alterno',
                'description' => '3 días a la semana',
                'start_time' => '07:00:00',
                'end_time' => '12:40:00',
                'days_per_week' => 3,
                'display_order' => 2,
            ],
            [
                'code' => 'GUARDERIA',
                'name' => 'Guardería',
                'description' => '5 días a la semana',
                'start_time' => '07:00:00',
                'end_time' => '17:00:00',
                'days_per_week' => 5,
                'display_order' => 3,
            ],
        ];

        foreach ($schedules as $schedule) {
            Schedule::updateOrCreate(['code' => $schedule['code']], $schedule + ['is_active' => true]);
        }
    }
}
