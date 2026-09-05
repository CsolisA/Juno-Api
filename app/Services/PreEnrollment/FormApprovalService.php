<?php

namespace App\Services\PreEnrollment;

use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Enums\GuardianRole;
use App\Enums\PreEnrollmentFormStatus;
use App\Models\Enrollment;
use App\Models\Guardian;
use App\Models\PreEnrollmentForm;
use App\Services\PreEnrollment\Exceptions\FormNotSubmittedException;
use Illuminate\Support\Facades\DB;

/**
 * Converts a submitted PreEnrollmentForm into real records — the "director approves" step per
 * §9 Q2. v1 combines what the spec models as separate Approved/Applied states into one action:
 * approving a submitted form writes to live tables and creates the projected enrollment in the
 * same call, stamping both reviewed_* and applied_at together.
 */
class FormApprovalService
{
    /**
     * @throws FormNotSubmittedException
     */
    public function approve(PreEnrollmentForm $form, int $adminUserId): Enrollment
    {
        if ($form->status !== PreEnrollmentFormStatus::Submitted) {
            throw FormNotSubmittedException::forForm($form->id);
        }

        return DB::transaction(function () use ($form, $adminUserId) {
            $snapshot = $form->submitted_snapshot ?? [];

            foreach ([GuardianRole::Mother, GuardianRole::Father] as $role) {
                $this->upsertGuardian($form->family_id, $role, $snapshot);
            }

            $this->updateStudent($form, $snapshot);

            $enrollment = Enrollment::create([
                'student_id' => $form->student_id,
                'academic_year_id' => $form->campaign->academic_year_id,
                'grade_id' => $form->target_grade_id,
                'group_id' => null,
                'schedule_id' => $this->value($snapshot, 'student.scheduleId'),
                'status' => EnrollmentStatus::Projected,
                'source' => EnrollmentSource::PreEnrollment,
                'pre_enrollment_form_id' => $form->id,
                'transport_type' => $this->value($snapshot, 'student.transportType'),
                'medical_conditions' => $this->value($snapshot, 'student.medicalConditions'),
                'diagnosis' => $this->value($snapshot, 'student.diagnosis'),
                'takes_medication' => (bool) $this->value($snapshot, 'student.takesMedication'),
                'medication_details' => $this->value($snapshot, 'student.medicationDetails'),
                'practices_sport' => (bool) $this->value($snapshot, 'student.practicesSport'),
                'sport_details' => $this->value($snapshot, 'student.sportDetails'),
                'extra_classes' => (bool) $this->value($snapshot, 'student.extraClasses'),
                'extra_classes_detail' => $this->value($snapshot, 'student.extraClassesDetail'),
                'uniform_size' => $this->value($snapshot, 'student.uniformSize'),
                'uniform_qty_shirt' => $this->value($snapshot, 'student.uniformQtyShirt'),
                'uniform_qty_short' => $this->value($snapshot, 'student.uniformQtyShort'),
            ]);

            $form->update([
                'projected_enrollment_id' => $enrollment->id,
                'applied_at' => now(),
                'reviewed_at' => now(),
                'reviewed_by' => $adminUserId,
                'status' => PreEnrollmentFormStatus::Applied,
            ]);

            return $enrollment;
        });
    }

    /**
     * @param  array<string, array{value: mixed, sourceValue: mixed}>  $snapshot
     */
    private function upsertGuardian(int $familyId, GuardianRole $role, array $snapshot): void
    {
        $prefix = "guardians.{$role->value}.";

        if ($this->value($snapshot, "{$prefix}name") === null) {
            return;
        }

        $fields = [
            'name' => $this->value($snapshot, "{$prefix}name"),
            'last_name_one' => $this->value($snapshot, "{$prefix}lastNameOne"),
            'last_name_two' => $this->value($snapshot, "{$prefix}lastNameTwo"),
            'nationality' => $this->value($snapshot, "{$prefix}nationality"),
            'id_type' => $this->value($snapshot, "{$prefix}idType"),
            'id_number' => $this->value($snapshot, "{$prefix}idNumber"),
            'birth_date' => $this->value($snapshot, "{$prefix}birthDate"),
            'marital_status' => $this->value($snapshot, "{$prefix}maritalStatus"),
            'religion' => $this->value($snapshot, "{$prefix}religion"),
            'education_level' => $this->value($snapshot, "{$prefix}educationLevel"),
            'occupation' => $this->value($snapshot, "{$prefix}occupation"),
            'workplace' => $this->value($snapshot, "{$prefix}workplace"),
            'mobile_phone' => $this->value($snapshot, "{$prefix}mobilePhone"),
            'work_phone' => $this->value($snapshot, "{$prefix}workPhone"),
            'lives_with_child' => $this->value($snapshot, "{$prefix}livesWithChild"),
            'address' => $this->value($snapshot, "{$prefix}address"),
            'email' => $this->value($snapshot, "{$prefix}email"),
            'uses_whatsapp' => $this->value($snapshot, "{$prefix}usesWhatsapp"),
            'uses_facebook' => $this->value($snapshot, "{$prefix}usesFacebook"),
            'uses_instagram' => $this->value($snapshot, "{$prefix}usesInstagram"),
            'uses_threads' => $this->value($snapshot, "{$prefix}usesThreads"),
        ];

        // Nullable/defaulted columns (id_type, lives_with_child, uses_*) must be omitted rather
        // than explicitly set to null — several have DB defaults that an explicit NULL bypasses,
        // tripping their NOT NULL constraint.
        Guardian::updateOrCreate(
            ['family_id' => $familyId, 'role' => $role],
            array_filter($fields, fn ($value) => $value !== null),
        );
    }

    /**
     * @param  array<string, array{value: mixed, sourceValue: mixed}>  $snapshot
     */
    private function updateStudent(PreEnrollmentForm $form, array $snapshot): void
    {
        $fields = [
            'birth_date' => $this->value($snapshot, 'student.birthDate'),
            'id_type' => $this->value($snapshot, 'student.idType'),
            'id_number' => $this->value($snapshot, 'student.idNumber'),
            'nationality' => $this->value($snapshot, 'student.nationality'),
            'blood_type' => $this->value($snapshot, 'student.bloodType'),
            'province' => $this->value($snapshot, 'student.province'),
            'canton' => $this->value($snapshot, 'student.canton'),
            'address' => $this->value($snapshot, 'student.address'),
            'phone' => $this->value($snapshot, 'student.phone'),
            'insurance_policy_number' => $this->value($snapshot, 'student.insurancePolicyNumber'),
        ];

        $form->student->update(array_filter($fields, fn ($value) => $value !== null));
    }

    /**
     * @param  array<string, array{value: mixed, sourceValue: mixed}>  $snapshot
     */
    private function value(array $snapshot, string $path): mixed
    {
        return $snapshot[$path]['value'] ?? null;
    }
}
