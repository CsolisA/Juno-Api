<?php

namespace Tests\Unit\Services\PreEnrollment;

use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Enums\PreEnrollmentFormStatus;
use App\Models\AcademicYear;
use App\Models\AdminUser;
use App\Models\Family;
use App\Models\Grade;
use App\Models\Guardian;
use App\Models\Kinder;
use App\Models\PreEnrollmentCampaign;
use App\Models\PreEnrollmentForm;
use App\Models\Schedule;
use App\Models\Student;
use App\Services\PreEnrollment\Exceptions\FormNotSubmittedException;
use App\Services\PreEnrollment\FormApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormApprovalServiceTest extends TestCase
{
    use RefreshDatabase;

    private FormApprovalService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new FormApprovalService;
    }

    private function submittedForm(): PreEnrollmentForm
    {
        $kinder = Kinder::factory()->create();
        $academicYear = AcademicYear::factory()->current()->create(['kinder_id' => $kinder->id]);
        $currentGrade = Grade::factory()->create(['is_final' => false]);
        $targetGrade = Grade::factory()->create(['is_final' => false]);
        $schedule = Schedule::factory()->create();
        $family = Family::factory()->create(['kinder_id' => $kinder->id]);
        $student = Student::factory()->create(['family_id' => $family->id]);
        $campaign = PreEnrollmentCampaign::factory()->create(['academic_year_id' => $academicYear->id]);

        return PreEnrollmentForm::factory()->create([
            'campaign_id' => $campaign->id,
            'student_id' => $student->id,
            'family_id' => $family->id,
            'current_grade_id' => $currentGrade->id,
            'target_grade_id' => $targetGrade->id,
            'status' => PreEnrollmentFormStatus::Submitted,
            'submitted_snapshot' => [
                'guardians.mother.name' => ['value' => 'María', 'sourceValue' => null],
                'guardians.mother.lastNameOne' => ['value' => 'Mora', 'sourceValue' => null],
                'guardians.mother.nationality' => ['value' => 'Costarricense', 'sourceValue' => null],
                'guardians.mother.idNumber' => ['value' => '1-1111-1111', 'sourceValue' => null],
                'guardians.mother.maritalStatus' => ['value' => 'casada', 'sourceValue' => null],
                'guardians.mother.educationLevel' => ['value' => 'universidad', 'sourceValue' => null],
                'guardians.mother.occupation' => ['value' => 'Ingeniera', 'sourceValue' => null],
                'guardians.mother.workplace' => ['value' => 'ACME', 'sourceValue' => null],
                'guardians.mother.mobilePhone' => ['value' => '8888-1111', 'sourceValue' => null],
                'guardians.mother.address' => ['value' => 'San José', 'sourceValue' => null],
                'guardians.mother.email' => ['value' => 'maria@example.test', 'sourceValue' => null],
                'student.scheduleId' => ['value' => $schedule->id, 'sourceValue' => $schedule->id],
                'student.transportType' => ['value' => 'family', 'sourceValue' => 'family'],
                'student.bloodType' => ['value' => 'O+', 'sourceValue' => null],
            ],
        ]);
    }

    public function test_approve_creates_guardian_and_projected_enrollment(): void
    {
        $admin = AdminUser::factory()->create();
        $form = $this->submittedForm();

        $enrollment = $this->service->approve($form, $admin->id);

        $this->assertSame(EnrollmentStatus::Projected, $enrollment->status);
        $this->assertSame(EnrollmentSource::PreEnrollment, $enrollment->source);
        $this->assertNull($enrollment->group_id);
        $this->assertSame($form->id, $enrollment->pre_enrollment_form_id);

        $guardian = Guardian::where('family_id', $form->family_id)->where('role', 'mother')->firstOrFail();
        $this->assertSame('María', $guardian->name);
        $this->assertSame('maria@example.test', $guardian->email);

        $form->refresh();
        $this->assertSame(PreEnrollmentFormStatus::Applied, $form->status);
        $this->assertSame($enrollment->id, $form->projected_enrollment_id);
        $this->assertSame($admin->id, $form->reviewed_by);
        $this->assertNotNull($form->applied_at);

        $student = $form->student->fresh();
        $this->assertSame('O+', $student->blood_type);
    }

    public function test_approve_is_rejected_when_not_submitted(): void
    {
        $admin = AdminUser::factory()->create();
        $form = PreEnrollmentForm::factory()->create(['status' => PreEnrollmentFormStatus::Pending]);

        $this->expectException(FormNotSubmittedException::class);

        $this->service->approve($form, $admin->id);
    }

    public function test_approve_updates_existing_guardian_instead_of_duplicating(): void
    {
        $admin = AdminUser::factory()->create();
        $form = $this->submittedForm();
        Guardian::factory()->create(['family_id' => $form->family_id, 'role' => 'mother', 'name' => 'Old Name']);

        $this->service->approve($form, $admin->id);

        $this->assertSame(1, Guardian::where('family_id', $form->family_id)->where('role', 'mother')->count());
        $this->assertSame('María', Guardian::where('family_id', $form->family_id)->where('role', 'mother')->first()->name);
    }
}
