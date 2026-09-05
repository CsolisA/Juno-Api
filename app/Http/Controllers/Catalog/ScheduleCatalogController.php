<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ScheduleCatalogController extends Controller
{
    /**
     * Schedules offered for a given grade, via the `schedule_grade` pivot. Per the pivot's
     * own design intent, a grade with no pivot rows at all means "all schedules allowed" —
     * a missing seed should never silently block a family from picking a schedule.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'gradeId' => ['nullable', 'integer', 'exists:grades,id'],
        ]);

        $query = Schedule::where('is_active', true)->orderBy('display_order');

        if (isset($data['gradeId'])) {
            $grade = Grade::findOrFail($data['gradeId']);

            $allowedScheduleIds = DB::table('schedule_grade')
                ->where('grade_id', $grade->id)
                ->where('is_active', true)
                ->pluck('schedule_id');

            $hasPivotRows = DB::table('schedule_grade')->where('grade_id', $grade->id)->exists();

            if ($hasPivotRows) {
                $query->whereIn('id', $allowedScheduleIds);
            }
        }

        $schedules = $query->get();

        return response()->json($schedules->map(fn (Schedule $schedule) => [
            'id' => $schedule->id,
            'code' => $schedule->code,
            'name' => $schedule->name,
            'description' => $schedule->description,
            'startTime' => $schedule->start_time?->format('H:i'),
            'endTime' => $schedule->end_time?->format('H:i'),
            'daysPerWeek' => $schedule->days_per_week,
        ]));
    }
}
