<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AcademicYearStatus;
use App\Enums\EnrollmentStatus;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\Enrollment;
use App\Models\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $data = $request->validate([
            'academicYear' => [
                'required',
                'integer',
                Rule::exists('academic_years', 'id')->where('kinder_id', $admin->kinder_id),
            ],
        ]);

        $groups = Group::where('academic_year_id', $data['academicYear'])
            ->with(['grade', 'professor', 'assistant'])
            ->withCount(['enrollments as students_count' => fn ($query) => $query->where('status', '!=', EnrollmentStatus::Withdrawn)])
            ->get();

        return response()->json($groups->map(fn (Group $group) => $this->formatSummary($group)));
    }

    public function show(Request $request, int $groupId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $group = $this->forAdmin($groupId, $admin);

        return response()->json($this->formatDetail($group));
    }

    /**
     * The authenticated staff member's own currently active group (professor or assistant seat,
     * academic year status = activo) — professors/assistants are limited to this, they can't
     * browse other groups (see the `director` middleware on index/show/update).
     */
    public function mine(Request $request): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $group = Group::whereHas(
            'academicYear',
            fn ($query) => $query->where('kinder_id', $admin->kinder_id)->where('status', AcademicYearStatus::Activo),
        )
            ->where(fn ($query) => $query->where('professor_id', $admin->id)->orWhere('assistant_id', $admin->id))
            ->first();

        abort_unless($group, 404, 'No tienes un grupo activo asignado.');

        return response()->json($this->formatDetail($group));
    }

    public function update(Request $request, int $groupId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $group = $this->forAdmin($groupId, $admin);
        $group->load('academicYear');

        abort_if(
            $group->academicYear->status === AcademicYearStatus::Cerrado,
            422,
            'Este año lectivo está cerrado y no admite cambios.',
        );

        $data = $request->validate([
            'professorId' => ['sometimes', 'integer', Rule::exists('admin_users', 'id')->where('kinder_id', $admin->kinder_id)],
            'assistantId' => ['sometimes', 'nullable', 'integer', Rule::exists('admin_users', 'id')->where('kinder_id', $admin->kinder_id)],
        ]);

        $updates = [];
        if (array_key_exists('professorId', $data)) {
            $updates['professor_id'] = $data['professorId'];
        }
        if (array_key_exists('assistantId', $data)) {
            $updates['assistant_id'] = $data['assistantId'];
        }

        $group->update($updates);

        return response()->json($this->formatDetail($group));
    }

    /**
     * Fetch a group, 404ing if it doesn't belong to the given admin's kinder — admins from one
     * kinder must never be able to read or mutate another kinder's group by guessing ids.
     */
    private function forAdmin(int $id, AdminUser $admin): Group
    {
        return Group::whereHas('academicYear', fn ($query) => $query->where('kinder_id', $admin->kinder_id))
            ->findOrFail($id);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDetail(Group $group): array
    {
        $group->load(['grade', 'professor', 'assistant']);
        $group->loadCount(['enrollments as students_count' => fn ($query) => $query->where('status', '!=', EnrollmentStatus::Withdrawn)]);

        $roster = Enrollment::where('group_id', $group->id)
            ->where('status', '!=', EnrollmentStatus::Withdrawn)
            ->with('student')
            ->get();

        return [
            ...$this->formatSummary($group),
            'students' => $roster->map(fn (Enrollment $enrollment) => [
                'id' => $enrollment->student->id,
                'name' => trim("{$enrollment->student->name} {$enrollment->student->last_name} {$enrollment->student->last_name_two}"),
                'enrollmentStatus' => $enrollment->status->value,
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatSummary(Group $group): array
    {
        return [
            'id' => $group->id,
            'grade' => ['id' => $group->grade->id, 'name' => $group->grade->name],
            'professor' => $group->professor ? ['id' => $group->professor->id, 'name' => $group->professor->name] : null,
            'assistant' => $group->assistant ? ['id' => $group->assistant->id, 'name' => $group->assistant->name] : null,
            'studentsCount' => $group->students_count,
        ];
    }
}
