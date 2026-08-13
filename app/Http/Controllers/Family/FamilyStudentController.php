<?php

namespace App\Http\Controllers\Family;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Family;
use App\Models\Group;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyStudentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Family $family */
        $family = $request->user();

        $academicYear = AcademicYear::current($family->kinder_id);

        $students = $family->students()
            ->with(['groups' => function ($query) use ($academicYear): void {
                $query->when(
                    $academicYear,
                    fn ($query) => $query->where('academic_year_id', $academicYear->id),
                    fn ($query) => $query->whereRaw('1 = 0'),
                )->with(['grade', 'academicYear', 'professor', 'assistant']);
            }])
            ->get();

        return response()->json($students->map(fn (Student $student) => $this->formatStudent($student)));
    }

    /**
     * @return array<string, mixed>
     */
    private function formatStudent(Student $student): array
    {
        $group = $student->groups->first();

        return [
            'id' => $student->id,
            'name' => $student->name,
            'lastname' => $student->last_name,
            'lastNameTwo' => $student->last_name_two,
            'transportType' => $student->transport_type?->value,
            'group' => $group ? $this->formatGroup($group) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatGroup(Group $group): array
    {
        return [
            'id' => $group->id,
            'grade' => [
                'id' => $group->grade->id,
                'name' => $group->grade->name,
            ],
            'academicYear' => [
                'id' => $group->academicYear->id,
                'year' => (string) $group->academicYear->year,
            ],
            'professor' => $group->professor ? [
                'id' => $group->professor->id,
                'name' => $group->professor->name,
                'phone' => $group->professor->phone,
            ] : null,
            'assistant' => $group->assistant ? [
                'id' => $group->assistant->id,
                'name' => $group->assistant->name,
            ] : null,
        ];
    }
}
