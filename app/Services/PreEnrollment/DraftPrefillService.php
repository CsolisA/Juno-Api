<?php

namespace App\Services\PreEnrollment;

use App\Models\Enrollment;
use App\Models\Family;
use App\Models\Guardian;
use App\Models\PreEnrollmentForm;
use App\Models\Student;

/**
 * Builds the initial draft_payload for a family draft / student form when a campaign opens —
 * "confirm, don't retype" per §2: every field we already know is pre-filled with its current
 * value, stored alongside itself as `sourceValue` so it's never mistaken for something the
 * family typed. draft_payload is a FLAT map of dot-path => {value, sourceValue} (§6.5) — no
 * real nested JSON tree is built, since only Eloquent/controllers ever read these paths back.
 *
 * v1 field coverage is intentionally a defensible subset of the full paper boleta, not
 * exhaustive — fields with no existing column (e.g. religion history, sibling reuse) are simply
 * left blank for the family to fill, which is expected and fine per §2.1.
 */
class DraftPrefillService
{
    /**
     * @return array<string, array{value: mixed, sourceValue: mixed}>
     */
    public function prefillFamilyDraft(Family $family): array
    {
        $payload = [];

        foreach (['mother', 'father'] as $role) {
            /** @var Guardian|null $guardian */
            $guardian = $family->guardians->firstWhere('role', $role);

            if (! $guardian) {
                continue;
            }

            foreach ($this->guardianFields($guardian) as $field => $value) {
                $payload["guardians.{$role}.{$field}"] = $this->entry($value);
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function guardianFields(Guardian $guardian): array
    {
        return [
            'name' => $guardian->name,
            'lastNameOne' => $guardian->last_name_one,
            'lastNameTwo' => $guardian->last_name_two,
            'nationality' => $guardian->nationality,
            'idType' => $guardian->id_type?->value,
            'idNumber' => $guardian->id_number,
            'birthDate' => $guardian->birth_date?->toDateString(),
            'maritalStatus' => $guardian->marital_status,
            'religion' => $guardian->religion,
            'educationLevel' => $guardian->education_level,
            'occupation' => $guardian->occupation,
            'workplace' => $guardian->workplace,
            'mobilePhone' => $guardian->mobile_phone,
            'workPhone' => $guardian->work_phone,
            'livesWithChild' => $guardian->lives_with_child,
            'address' => $guardian->address,
            'email' => $guardian->email,
            'usesWhatsapp' => $guardian->uses_whatsapp,
            'usesFacebook' => $guardian->uses_facebook,
            'usesInstagram' => $guardian->uses_instagram,
            'usesThreads' => $guardian->uses_threads,
        ];
    }

    /**
     * @return array<string, array{value: mixed, sourceValue: mixed}>
     */
    public function prefillFormDraft(PreEnrollmentForm $form): array
    {
        $student = $form->student;
        $latestEnrollment = $student->enrollments()->latest('id')->first();

        $payload = [];

        foreach ($this->studentFields($student) as $field => $value) {
            $payload["student.{$field}"] = $this->entry($value);
        }

        if ($latestEnrollment) {
            foreach ($this->enrollmentFields($latestEnrollment) as $field => $value) {
                $payload["student.{$field}"] = $this->entry($value);
            }
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function studentFields(Student $student): array
    {
        return [
            'birthDate' => $student->birth_date?->toDateString(),
            'idType' => $student->id_type?->value,
            'idNumber' => $student->id_number,
            'nationality' => $student->nationality,
            'bloodType' => $student->blood_type,
            'province' => $student->province,
            'canton' => $student->canton,
            'address' => $student->address,
            'phone' => $student->phone,
            'insurancePolicyNumber' => $student->insurance_policy_number,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function enrollmentFields(Enrollment $enrollment): array
    {
        return [
            'scheduleId' => $enrollment->schedule_id,
            'transportType' => $enrollment->transport_type?->value,
            'medicalConditions' => $enrollment->medical_conditions,
            'diagnosis' => $enrollment->diagnosis,
            'takesMedication' => $enrollment->takes_medication,
            'medicationDetails' => $enrollment->medication_details,
            'practicesSport' => $enrollment->practices_sport,
            'sportDetails' => $enrollment->sport_details,
            'extraClasses' => $enrollment->extra_classes,
            'extraClassesDetail' => $enrollment->extra_classes_detail,
            'uniformSize' => $enrollment->uniform_size,
            'uniformQtyShirt' => $enrollment->uniform_qty_shirt,
            'uniformQtyShort' => $enrollment->uniform_qty_short,
        ];
    }

    /**
     * @return array{value: mixed, sourceValue: mixed}
     */
    private function entry(mixed $value): array
    {
        return ['value' => $value, 'sourceValue' => $value];
    }
}
