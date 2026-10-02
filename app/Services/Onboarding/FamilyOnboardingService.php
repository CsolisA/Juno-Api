<?php

namespace App\Services\Onboarding;

use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Enums\FamilyInviteStatus;
use App\Enums\GuardianRole;
use App\Enums\IdType;
use App\Enums\ReferralSource;
use App\Enums\TransportType;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\Family;
use App\Models\FamilyInvite;
use App\Models\FamilyPasswordReset;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Guardian;
use App\Models\Schedule;
use App\Models\Student;
use App\Notifications\FamilyWelcomeNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FamilyOnboardingService
{
    private const WELCOME_TOKEN_VALID_DAYS = 7;

    public const DUPLICATE_ID_MESSAGE = 'Este número de identificación no puede registrarse con este enlace. Comunícate con la escuela.';

    /**
     * Validation rules for the full wizard payload (camelCase, like the rest of the API).
     *
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'family' => ['sometimes', 'array'],
            'family.aboutUs' => ['nullable', 'string', 'max:255'],
            'family.referralSource' => ['nullable', Rule::enum(ReferralSource::class)],

            'guardians' => ['required', 'array', 'min:1', 'max:3'],
            'guardians.*.role' => ['required', Rule::enum(GuardianRole::class)],
            'guardians.*.name' => ['required', 'string', 'max:255'],
            'guardians.*.lastNameOne' => ['required', 'string', 'max:255'],
            'guardians.*.lastNameTwo' => ['nullable', 'string', 'max:255'],
            'guardians.*.nationality' => ['required', 'string', 'max:255'],
            'guardians.*.idType' => ['nullable', Rule::enum(IdType::class)],
            'guardians.*.idNumber' => ['required', 'string', 'max:255'],
            'guardians.*.birthDate' => ['nullable', 'date', 'before:today'],
            'guardians.*.maritalStatus' => ['required', 'string', 'max:255'],
            'guardians.*.religion' => ['nullable', 'string', 'max:255'],
            'guardians.*.educationLevel' => ['required', 'string', 'max:255'],
            'guardians.*.occupation' => ['required', 'string', 'max:255'],
            'guardians.*.workplace' => ['required', 'string', 'max:255'],
            'guardians.*.mobilePhone' => ['required', 'string', 'max:255'],
            'guardians.*.workPhone' => ['nullable', 'string', 'max:255'],
            'guardians.*.livesWithChild' => ['nullable', 'boolean'],
            'guardians.*.address' => ['required', 'string', 'max:255'],
            'guardians.*.email' => ['required', 'email', 'max:255'],
            'guardians.*.usesWhatsapp' => ['nullable', 'boolean'],
            'guardians.*.usesFacebook' => ['nullable', 'boolean'],
            'guardians.*.usesInstagram' => ['nullable', 'boolean'],
            'guardians.*.usesThreads' => ['nullable', 'boolean'],

            'students' => ['required', 'array', 'min:1', 'max:10'],
            'students.*.name' => ['required', 'string', 'max:255'],
            'students.*.lastName' => ['required', 'string', 'max:255'],
            'students.*.lastNameTwo' => ['required', 'string', 'max:255'],
            'students.*.idType' => ['nullable', Rule::enum(IdType::class)],
            'students.*.idNumber' => ['required', 'string', 'max:255', 'distinct', Rule::unique('students', 'id_number')],
            'students.*.birthDate' => ['required', 'date', 'before:today'],
            'students.*.nationality' => ['required', 'string', 'max:255'],
            'students.*.province' => ['required', 'string', 'max:255'],
            'students.*.canton' => ['required', 'string', 'max:255'],
            'students.*.address' => ['required', 'string', 'max:255'],
            'students.*.phone' => ['nullable', 'string', 'max:255'],
            'students.*.bloodType' => ['nullable', 'string', 'max:10'],
            'students.*.insurancePolicyNumber' => ['nullable', 'string', 'max:255'],
            'students.*.gradeId' => ['required', 'integer', Rule::exists('grades', 'id')],
            'students.*.scheduleId' => ['nullable', 'integer', Rule::exists('schedules', 'id')],
            'students.*.transportType' => ['nullable', Rule::enum(TransportType::class)],
            'students.*.medicalConditions' => ['nullable', 'string'],
            'students.*.diagnosis' => ['nullable', 'string'],
            'students.*.takesMedication' => ['nullable', 'boolean'],
            'students.*.medicationDetails' => ['nullable', 'string', 'max:255'],
            'students.*.practicesSport' => ['nullable', 'boolean'],
            'students.*.sportDetails' => ['nullable', 'string', 'max:255'],
            'students.*.extraClasses' => ['nullable', 'boolean'],
            'students.*.extraClassesDetail' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'students.*.idNumber.unique' => self::DUPLICATE_ID_MESSAGE,
            'students.*.idNumber.distinct' => 'Este número de identificación está repetido en la solicitud.',
        ];
    }

    /**
     * Extra cross-field checks the rule array can't express: password-reset emails only go to the
     * mother/father (see FamilyPasswordController::forgot), so at least one of them must be present,
     * and the same role can't be listed twice.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public static function guardianErrors(array $data): array
    {
        $roles = collect($data['guardians'] ?? [])->pluck('role');

        if (! $roles->contains(GuardianRole::Mother->value) && ! $roles->contains(GuardianRole::Father->value)) {
            return ['guardians' => 'Debe registrarse al menos a la madre o al padre.'];
        }

        foreach ([GuardianRole::Mother, GuardianRole::Father] as $role) {
            if ($roles->filter(fn ($value) => $value === $role->value)->count() > 1) {
                return ['guardians' => 'Solo se puede registrar una madre y un padre.'];
            }
        }

        return [];
    }

    /**
     * Creates the family, guardians, students and one projected enrollment per child — all in a
     * single transaction that re-checks the invite under a row lock, so a double submit (two
     * tabs, or both parents) can only ever succeed once.
     *
     * @param  array<string, mixed>  $data  validated payload (see rules())
     */
    public function submit(FamilyInvite $invite, array $data): Family
    {
        $errors = self::guardianErrors($data);

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $year = $this->onboardingYear($invite);

        $welcomeToken = null;

        $family = DB::transaction(function () use ($invite, $data, $year, &$welcomeToken) {
            $locked = FamilyInvite::whereKey($invite->id)->lockForUpdate()->firstOrFail();

            abort_unless($locked->isUsable(), 404);

            $family = $this->createFamily($locked, $data);
            $this->createGuardians($family, $data['guardians']);

            foreach ($data['students'] as $studentData) {
                $this->createStudent($family, $year, $studentData);
            }

            $locked->update([
                'status' => FamilyInviteStatus::Submitted,
                'submitted_at' => now(),
                'family_id' => $family->id,
                'draft_payload' => null,
            ]);

            $welcomeToken = Str::random(64);

            FamilyPasswordReset::create([
                'family_id' => $family->id,
                'token' => $welcomeToken,
                'expires_at' => now()->addDays(self::WELCOME_TOKEN_VALID_DAYS),
            ]);

            return $family;
        });

        $this->sendWelcome($family, $welcomeToken);

        return $family;
    }

    private function onboardingYear(FamilyInvite $invite): AcademicYear
    {
        $year = AcademicYear::where('kinder_id', $invite->kinder_id)
            ->where('year', config('app.onboarding_year'))
            ->first();

        if (! $year) {
            throw new HttpException(503, 'El año lectivo de ingreso no está configurado. Comunícate con la escuela.');
        }

        return $year;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createFamily(FamilyInvite $invite, array $data): Family
    {
        $firstStudent = $data['students'][0];

        return Family::create([
            'kinder_id' => $invite->kinder_id,
            'last_name_one' => $firstStudent['lastName'],
            'last_name_two' => $firstStudent['lastNameTwo'],
            'user' => $this->uniqueUser($firstStudent['lastName'], $firstStudent['lastNameTwo']),
            // Never shown to anyone: the family sets their own through the welcome email link.
            'password' => Str::random(40),
            'about_us' => $data['family']['aboutUs'] ?? null,
            'referral_source' => $data['family']['referralSource'] ?? null,
        ]);
    }

    private function uniqueUser(string $lastNameOne, string $lastNameTwo): string
    {
        $base = Str::slug("{$lastNameOne} {$lastNameTwo}", '.') ?: 'familia';

        do {
            $user = $base.random_int(1000, 9999);
        } while (Family::where('user', $user)->exists());

        return $user;
    }

    /**
     * @param  array<int, array<string, mixed>>  $guardians
     */
    private function createGuardians(Family $family, array $guardians): void
    {
        foreach ($guardians as $index => $guardian) {
            Guardian::create([
                'family_id' => $family->id,
                'role' => $guardian['role'],
                'name' => $guardian['name'],
                'last_name_one' => $guardian['lastNameOne'],
                'last_name_two' => $guardian['lastNameTwo'] ?? null,
                'nationality' => $guardian['nationality'],
                'id_type' => $guardian['idType'] ?? IdType::Cedula->value,
                'id_number' => $guardian['idNumber'],
                'birth_date' => $guardian['birthDate'] ?? null,
                'marital_status' => $guardian['maritalStatus'],
                'religion' => $guardian['religion'] ?? null,
                'education_level' => $guardian['educationLevel'],
                'occupation' => $guardian['occupation'],
                'workplace' => $guardian['workplace'],
                'mobile_phone' => $guardian['mobilePhone'],
                'work_phone' => $guardian['workPhone'] ?? null,
                'lives_with_child' => $guardian['livesWithChild'] ?? true,
                'address' => $guardian['address'],
                'email' => $guardian['email'],
                'uses_whatsapp' => $guardian['usesWhatsapp'] ?? false,
                'uses_facebook' => $guardian['usesFacebook'] ?? false,
                'uses_instagram' => $guardian['usesInstagram'] ?? false,
                'uses_threads' => $guardian['usesThreads'] ?? false,
                'is_primary_contact' => $index === 0,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createStudent(Family $family, AcademicYear $year, array $data): void
    {
        $grade = Grade::findOrFail($data['gradeId']);

        // One catch-all group per level (Year2027Seeder). Not enforced by a unique index so a level
        // can still be split into several groups later; the oldest one receives new families.
        $group = Group::where('grade_id', $grade->id)
            ->where('academic_year_id', $year->id)
            ->orderBy('id')
            ->first();

        if (! $group) {
            throw new HttpException(503, "No hay un grupo configurado para {$grade->name}. Comunícate con la escuela.");
        }

        $schedule = $this->resolveSchedule($grade, $data['scheduleId'] ?? null);

        $student = Student::create([
            'family_id' => $family->id,
            'name' => $data['name'],
            'last_name' => $data['lastName'],
            'last_name_two' => $data['lastNameTwo'],
            'id_type' => $data['idType'] ?? IdType::Cedula->value,
            'id_number' => $data['idNumber'],
            'birth_date' => $data['birthDate'],
            'nationality' => $data['nationality'],
            'province' => $data['province'],
            'canton' => $data['canton'],
            'address' => $data['address'],
            'phone' => $data['phone'] ?? null,
            'blood_type' => $data['bloodType'] ?? null,
            'insurance_policy_number' => $data['insurancePolicyNumber'] ?? null,
        ]);

        // Fees, uniform and document flags stay null/false: the director fills them in later.
        Enrollment::create([
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'group_id' => $group->id,
            'grade_id' => $grade->id,
            'schedule_id' => $schedule->id,
            'status' => EnrollmentStatus::Projected,
            'source' => EnrollmentSource::Manual,
            'transport_type' => $data['transportType'] ?? null,
            'medical_conditions' => $data['medicalConditions'] ?? null,
            'diagnosis' => $data['diagnosis'] ?? null,
            'takes_medication' => $data['takesMedication'] ?? false,
            'medication_details' => $data['medicationDetails'] ?? null,
            'practices_sport' => $data['practicesSport'] ?? false,
            'sport_details' => $data['sportDetails'] ?? null,
            'extra_classes' => $data['extraClasses'] ?? false,
            'extra_classes_detail' => $data['extraClassesDetail'] ?? null,
        ]);

        // Family dashboard/history read this pivot rather than enrollments.group_id.
        $student->groups()->attach($group->id);
    }

    /**
     * The schedule the family picked, if it's offered for the grade; otherwise the first active
     * one the grade offers. Mirrors ScheduleCatalogController: a grade with no `schedule_grade`
     * rows allows every active schedule.
     */
    private function resolveSchedule(Grade $grade, ?int $scheduleId): Schedule
    {
        $allowed = $this->allowedSchedules($grade);

        if ($scheduleId !== null) {
            $picked = $allowed->firstWhere('id', $scheduleId);

            if (! $picked) {
                throw ValidationException::withMessages([
                    'students' => "El horario elegido no está disponible para {$grade->name}.",
                ]);
            }

            return $picked;
        }

        $first = $allowed->first();

        if (! $first) {
            throw new HttpException(503, "No hay horarios configurados para {$grade->name}. Comunícate con la escuela.");
        }

        return $first;
    }

    /**
     * @return Collection<int, Schedule>
     */
    private function allowedSchedules(Grade $grade): Collection
    {
        $query = Schedule::where('is_active', true)->orderBy('display_order');

        if (DB::table('schedule_grade')->where('grade_id', $grade->id)->exists()) {
            $query->whereIn('id', DB::table('schedule_grade')
                ->where('grade_id', $grade->id)
                ->where('is_active', true)
                ->pluck('schedule_id'));
        }

        return $query->get();
    }

    /**
     * Email problems must never undo or fail a submission that already committed; the admin can
     * resend access from the portal.
     */
    private function sendWelcome(Family $family, string $token): void
    {
        $emails = $family->guardians()
            ->whereIn('role', [GuardianRole::Mother->value, GuardianRole::Father->value])
            ->pluck('email')
            ->filter()
            ->unique();

        foreach ($emails as $email) {
            try {
                Notification::route('mail', $email)->notify(new FamilyWelcomeNotification($token, $family));
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }
}
