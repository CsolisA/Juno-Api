<?php

namespace App\Http\Controllers\Family;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\Group;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyHistoryController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var Family $family */
        $family = $request->user();

        $students = $family->students()->with(['groups.academicYear', 'groups.grade'])->get();

        return response()->json([
            'familySince' => $family->created_at?->toDateString(),
            'students' => $students->map(fn (Student $student) => [
                'id' => $student->id,
                'name' => $student->name,
                'enrollmentHistory' => $student->groups
                    ->sortBy(fn (Group $group) => $group->academicYear->year)
                    ->map(fn (Group $group) => [
                        'academicYear' => (string) $group->academicYear->year,
                        'grade' => $group->grade->name,
                    ])
                    ->values(),
            ]),
        ]);
    }
}
