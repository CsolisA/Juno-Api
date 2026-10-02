<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AcademicYearStatus;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentGroupController extends Controller
{
    /**
     * Reassigns a student to a different group. The target group's academic year determines
     * which of the student's enrollments gets updated — a student can hold one enrollment per
     * academic year, and `group_id` (plus `grade_id`, kept consistent with it) lives on that
     * enrollment rather than on the student record.
     */
    public function update(Request $request, int $studentId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $student = Student::whereHas('family', fn ($query) => $query->where('kinder_id', $admin->kinder_id))
            ->findOrFail($studentId);

        $data = $request->validate([
            'groupId' => [
                'required',
                'integer',
                Rule::exists('groups', 'id'),
            ],
        ]);

        $group = Group::whereHas('academicYear', fn ($query) => $query->where('kinder_id', $admin->kinder_id))
            ->with('academicYear')
            ->findOrFail($data['groupId']);

        abort_if(
            $group->academicYear->status === AcademicYearStatus::Cerrado,
            422,
            'Este año lectivo está cerrado y no admite cambios.',
        );

        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('academic_year_id', $group->academic_year_id)
            ->firstOrFail();

        $enrollment->update(['group_id' => $group->id, 'grade_id' => $group->grade_id]);

        // Family views read the `student_group` pivot, so keep it in step with the enrollment:
        // swap whatever group the student held in this academic year for the new one.
        $student->groups()->detach(
            Group::where('academic_year_id', $group->academic_year_id)->pluck('id'),
        );
        $student->groups()->attach($group->id);

        return response()->json([
            'id' => $student->id,
            'groupId' => $enrollment->group_id,
        ]);
    }
}
