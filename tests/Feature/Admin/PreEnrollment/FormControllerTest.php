<?php

namespace Tests\Feature\Admin\PreEnrollment;

use App\Enums\ActorType;
use App\Enums\PreEnrollmentFormStatus;
use App\Models\AcademicYear;
use App\Models\AdminUser;
use App\Models\Enrollment;
use App\Models\Family;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Kinder;
use App\Models\PreEnrollmentForm;
use App\Models\Student;
use App\Services\PreEnrollment\CampaignCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FormControllerTest extends TestCase
{
    use RefreshDatabase;

    private function submittedFormForAdmin(): array
    {
        Notification::fake();

        $kinder = Kinder::factory()->create();
        $admin = AdminUser::factory()->create(['kinder_id' => $kinder->id]);
        $grade = Grade::factory()->create(['order' => 0, 'is_final' => false]);
        Grade::factory()->create(['order' => 1, 'is_final' => true]);
        $academicYear = AcademicYear::factory()->current()->create(['kinder_id' => $kinder->id]);
        $group = Group::factory()->create(['grade_id' => $grade->id, 'academic_year_id' => $academicYear->id]);

        $family = Family::factory()->create(['kinder_id' => $kinder->id]);
        $student = Student::factory()->create(['family_id' => $family->id, 'blood_type' => 'O+']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'group_id' => $group->id,
            'grade_id' => $grade->id,
        ]);

        $campaign = (new CampaignCreationService)->create($academicYear->id, $admin->id);

        Sanctum::actingAs($admin, ['*']);
        $this->postJson("/api/admin/pre-enrollment/campaigns/{$campaign->id}/open")->assertOk();

        $form = PreEnrollmentForm::where('campaign_id', $campaign->id)->where('student_id', $student->id)->firstOrFail();
        $form->update([
            'status' => PreEnrollmentFormStatus::Submitted,
            'submitted_at' => now(),
            'submitted_snapshot' => array_merge($form->draft_payload, [
                'student.bloodType' => ['value' => 'A+', 'sourceValue' => 'O+'],
            ]),
        ]);

        return [$campaign, $form, $admin];
    }

    public function test_show_returns_current_proposed_and_changed_flag(): void
    {
        [$campaign, $form, $admin] = $this->submittedFormForAdmin();

        $response = $this->getJson("/api/admin/pre-enrollment/campaigns/{$campaign->id}/forms/{$form->id}")->assertOk();

        $field = $response->json('fields')['student.bloodType'];
        $this->assertSame('O+', $field['current']);
        $this->assertSame('A+', $field['proposed']);
        $this->assertTrue($field['changed']);
    }

    public function test_director_correction_writes_audit_row(): void
    {
        [$campaign, $form, $admin] = $this->submittedFormForAdmin();

        $this->patchJson("/api/admin/pre-enrollment/campaigns/{$campaign->id}/forms/{$form->id}", [
            'changes' => ['student.bloodType' => 'B+'],
        ])->assertOk();

        $this->assertDatabaseHas('pre_enrollment_field_changes', [
            'form_id' => $form->id,
            'field_path' => 'student.bloodType',
            'new_value' => 'B+',
            'actor_type' => ActorType::Admin->value,
            'actor_id' => $admin->id,
        ]);
    }

    public function test_approve_creates_enrollment_and_applies_form(): void
    {
        [$campaign, $form, $admin] = $this->submittedFormForAdmin();

        // Fill the household so FormApprovalService's guardian upsert has something to write.
        $form->update(['submitted_snapshot' => array_merge($form->submitted_snapshot, [
            'guardians.mother.name' => ['value' => 'Ana'],
            'guardians.mother.lastNameOne' => ['value' => 'Perez'],
            'guardians.mother.nationality' => ['value' => 'Costarricense'],
            'guardians.mother.idNumber' => ['value' => '1-1111-1111'],
            'guardians.mother.maritalStatus' => ['value' => 'soltera'],
            'guardians.mother.educationLevel' => ['value' => 'universidad'],
            'guardians.mother.occupation' => ['value' => 'Doctora'],
            'guardians.mother.workplace' => ['value' => 'Hospital'],
            'guardians.mother.mobilePhone' => ['value' => '8888-2222'],
            'guardians.mother.address' => ['value' => 'Heredia'],
            'guardians.mother.email' => ['value' => 'ana@example.test'],
        ])]);

        $response = $this->postJson("/api/admin/pre-enrollment/campaigns/{$campaign->id}/forms/{$form->id}/approve")->assertOk();

        $form->refresh();
        $this->assertSame(PreEnrollmentFormStatus::Applied, $form->status);
        $this->assertNotNull($form->projected_enrollment_id);
        $this->assertSame($form->projected_enrollment_id, $response->json('enrollmentId'));
    }

    public function test_reopen_sends_submitted_form_back_to_in_progress(): void
    {
        [$campaign, $form, $admin] = $this->submittedFormForAdmin();

        $this->postJson("/api/admin/pre-enrollment/campaigns/{$campaign->id}/forms/{$form->id}/reopen", [
            'note' => 'Falta el número de póliza.',
        ])->assertOk();

        $form->refresh();
        $this->assertSame(PreEnrollmentFormStatus::InProgress, $form->status);
        $this->assertSame('Falta el número de póliza.', $form->family_notes);
    }
}
