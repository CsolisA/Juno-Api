<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AcademicYearStatus;
use App\Enums\AdminUserType;
use App\Enums\EnrollmentStatus;
use App\Enums\IdType;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AdminUser;
use App\Models\Enrollment;
use App\Models\Group;
use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StudentController extends Controller
{
    /**
     * camelCase request key => students column, for the fields a director may edit here. Health,
     * transport and fee data live on Enrollment (reviewed annually) and aren't edited through
     * this endpoint.
     */
    private const FIELD_MAP = [
        'name' => 'name',
        'lastName' => 'last_name',
        'lastNameTwo' => 'last_name_two',
        'idType' => 'id_type',
        'idNumber' => 'id_number',
        'birthDate' => 'birth_date',
        'insurancePolicyNumber' => 'insurance_policy_number',
        'bloodType' => 'blood_type',
        'nationality' => 'nationality',
        'province' => 'province',
        'canton' => 'canton',
        'address' => 'address',
        'phone' => 'phone',
    ];

    /**
     * Director-only roster: every student for the kinder, split active/inactive by whether they
     * hold an Active enrollment in the current academic year.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $data = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
            'search' => ['nullable', 'string'],
        ]);

        $currentYear = AcademicYear::current($admin->kinder_id);

        $students = Student::whereHas('family', fn ($query) => $query->where('kinder_id', $admin->kinder_id))
            ->when(
                isset($data['search']),
                fn ($query) => $query->where(fn ($q) => $q
                    ->where('name', 'like', "%{$data['search']}%")
                    ->orWhere('last_name', 'like', "%{$data['search']}%")
                    ->orWhere('last_name_two', 'like', "%{$data['search']}%")),
            )
            ->with(['enrollments' => fn ($query) => $query->where('academic_year_id', $currentYear?->id)->with('grade')])
            ->orderBy('name')
            ->get();

        $rows = $students->map(function (Student $student) {
            $currentEnrollment = $student->enrollments->first();

            return [
                'id' => $student->id,
                'name' => trim("{$student->name} {$student->last_name} {$student->last_name_two}"),
                'birthDate' => $student->birth_date?->toDateString(),
                'status' => $currentEnrollment?->status === EnrollmentStatus::Active ? 'active' : 'inactive',
                'grade' => $currentEnrollment?->grade ? ['id' => $currentEnrollment->grade->id, 'name' => $currentEnrollment->grade->name] : null,
                'groupId' => $currentEnrollment?->group_id,
            ];
        });

        if (isset($data['status'])) {
            $rows = $rows->where('status', $data['status'])->values();
        }

        return response()->json($rows->values());
    }

    /**
     * Any authenticated staff member can view a student. Directors get the complete file;
     * professors/assistants get a reduced field set and only for a student currently in their
     * own active group — never students outside it, per the group-visibility rule.
     */
    public function show(Request $request, int $studentId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $student = Student::whereHas('family', fn ($query) => $query->where('kinder_id', $admin->kinder_id))
            ->with([
                'family.guardians',
                'authorizedPersons',
                'enrollments' => fn ($query) => $query->with(['grade', 'schedule'])->orderByDesc('academic_year_id'),
            ])
            ->findOrFail($studentId);

        if ($admin->type !== AdminUserType::Director) {
            $activeGroup = $this->activeGroupFor($admin);

            $inActiveGroup = $activeGroup && $student->enrollments->contains(
                fn (Enrollment $enrollment) => $enrollment->group_id === $activeGroup->id && $enrollment->status === EnrollmentStatus::Active,
            );

            abort_unless($inActiveGroup, 403, 'Solo puedes ver estudiantes de tu grupo activo.');

            return response()->json($this->formatBasic($student));
        }

        return response()->json($this->formatFull($student, $admin));
    }

    /**
     * Director-only edit of the student's own identity fields.
     */
    public function update(Request $request, int $studentId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $student = Student::whereHas('family', fn ($query) => $query->where('kinder_id', $admin->kinder_id))
            ->findOrFail($studentId);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'lastName' => ['sometimes', 'string', 'max:255'],
            'lastNameTwo' => ['sometimes', 'string', 'max:255'],
            'idType' => ['sometimes', Rule::in(array_map(fn (IdType $case) => $case->value, IdType::cases()))],
            'idNumber' => ['sometimes', 'string', Rule::unique('students', 'id_number')->ignore($student->id)],
            'birthDate' => ['sometimes', 'date'],
            'insurancePolicyNumber' => ['sometimes', 'nullable', 'string'],
            'bloodType' => ['sometimes', 'nullable', 'string'],
            'nationality' => ['sometimes', 'string'],
            'province' => ['sometimes', 'string'],
            'canton' => ['sometimes', 'string'],
            'address' => ['sometimes', 'string'],
            'phone' => ['sometimes', 'nullable', 'string'],
        ]);

        $mapped = [];
        foreach ($data as $key => $value) {
            $mapped[self::FIELD_MAP[$key]] = $value;
        }

        $student->update($mapped);

        $student->load([
            'family.guardians',
            'authorizedPersons',
            'enrollments' => fn ($query) => $query->with(['grade', 'schedule'])->orderByDesc('academic_year_id'),
        ]);

        return response()->json($this->formatFull($student, $admin));
    }

    /**
     * The admin's own currently active group (professor or assistant seat), or null — mirrors
     * GroupController::mine()'s lookup.
     */
    private function activeGroupFor(AdminUser $admin): ?Group
    {
        return Group::whereHas(
            'academicYear',
            fn ($query) => $query->where('kinder_id', $admin->kinder_id)->where('status', AcademicYearStatus::Activo),
        )
            ->where(fn ($query) => $query->where('professor_id', $admin->id)->orWhere('assistant_id', $admin->id))
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatBasic(Student $student): array
    {
        $enrollment = $student->enrollments->first();

        return [
            'id' => $student->id,
            'name' => $student->name,
            'lastName' => $student->last_name,
            'lastNameTwo' => $student->last_name_two,
            'birthDate' => $student->birth_date?->toDateString(),
            'bloodType' => $student->blood_type,
            'medicalConditions' => $enrollment?->medical_conditions,
            'diagnosis' => $enrollment?->diagnosis,
            'takesMedication' => $enrollment?->takes_medication,
            'medicationDetails' => $enrollment?->medication_details,
            'transportType' => $enrollment?->transport_type?->value,
            'schedule' => $enrollment?->schedule ? ['id' => $enrollment->schedule->id, 'name' => $enrollment->schedule->name] : null,
            'practicesSport' => $enrollment?->practices_sport,
            'sportDetails' => $enrollment?->sport_details,
            'extraClasses' => $enrollment?->extra_classes,
            'extraClassesDetail' => $enrollment?->extra_classes_detail,
            'guardians' => $student->family->guardians->map(fn (Guardian $guardian) => [
                'role' => $guardian->role->value,
                'name' => trim("{$guardian->name} {$guardian->last_name_one} {$guardian->last_name_two}"),
                'phone' => $guardian->mobile_phone,
            ])->values(),
            'authorizedContacts' => $student->authorizedPersons->map(fn ($contact) => [
                'name' => trim("{$contact->name} {$contact->last_name} {$contact->last_name_two}"),
                'phone' => $contact->phone,
                'relationship' => $contact->relationship,
                'photo' => $contact->photo,
                'pickUp' => $contact->pick_up,
                'isEmergencyContact' => $contact->is_emergency_contact,
            ])->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatFull(Student $student, AdminUser $admin): array
    {
        $currentYear = AcademicYear::current($admin->kinder_id);
        $currentEnrollment = $student->enrollments->firstWhere('academic_year_id', $currentYear?->id);

        return [
            ...$this->formatBasic($student),
            'idType' => $student->id_type?->value,
            'idNumber' => $student->id_number,
            'insurancePolicyNumber' => $student->insurance_policy_number,
            'nationality' => $student->nationality,
            'province' => $student->province,
            'canton' => $student->canton,
            'address' => $student->address,
            'phone' => $student->phone,
            'status' => $currentEnrollment?->status === EnrollmentStatus::Active ? 'active' : 'inactive',
            'family' => [
                'id' => $student->family->id,
                'lastNameOne' => $student->family->last_name_one,
                'lastNameTwo' => $student->family->last_name_two,
                'user' => $student->family->user,
            ],
            'guardians' => $student->family->guardians->map(fn (Guardian $guardian) => $this->formatFullGuardian($guardian))->values(),
            'currentEnrollment' => $currentEnrollment ? $this->formatEnrollment($currentEnrollment) : null,
            'enrollmentHistory' => $student->enrollments->map(fn (Enrollment $enrollment) => $this->formatEnrollment($enrollment))->values(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatFullGuardian(Guardian $guardian): array
    {
        return [
            'role' => $guardian->role->value,
            'name' => trim("{$guardian->name} {$guardian->last_name_one} {$guardian->last_name_two}"),
            'idType' => $guardian->id_type?->value,
            'idNumber' => $guardian->id_number,
            'birthDate' => $guardian->birth_date?->toDateString(),
            'nationality' => $guardian->nationality,
            'maritalStatus' => $guardian->marital_status,
            'educationLevel' => $guardian->education_level,
            'occupation' => $guardian->occupation,
            'workplace' => $guardian->workplace,
            'mobilePhone' => $guardian->mobile_phone,
            'workPhone' => $guardian->work_phone,
            'address' => $guardian->address,
            'email' => $guardian->email,
            'livesWithChild' => $guardian->lives_with_child,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatEnrollment(Enrollment $enrollment): array
    {
        return [
            'id' => $enrollment->id,
            'academicYearId' => $enrollment->academic_year_id,
            'grade' => $enrollment->grade ? ['id' => $enrollment->grade->id, 'name' => $enrollment->grade->name] : null,
            'groupId' => $enrollment->group_id,
            'schedule' => $enrollment->schedule ? ['id' => $enrollment->schedule->id, 'name' => $enrollment->schedule->name] : null,
            'status' => $enrollment->status->value,
            'enrollmentFeeAmount' => $enrollment->enrollment_fee_amount,
            'monthlyFeeAmount' => $enrollment->monthly_fee_amount,
            'uniformSize' => $enrollment->uniform_size,
            'uniformQtyShirt' => $enrollment->uniform_qty_shirt,
            'uniformQtyShort' => $enrollment->uniform_qty_short,
            'documents' => [
                'birthCert' => $enrollment->doc_birth_cert,
                'vaccineCard' => $enrollment->doc_vaccine_card,
                'motherId' => $enrollment->doc_mother_id,
                'fatherId' => $enrollment->doc_father_id,
                'authorizedIds' => $enrollment->doc_authorized_ids,
                'photos' => $enrollment->doc_photos,
                'serviceContract' => $enrollment->doc_service_contract,
            ],
        ];
    }
}
