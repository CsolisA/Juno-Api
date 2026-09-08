<?php

namespace Tests\Feature\Admin\PreEnrollment;

use App\Enums\EnrollmentStatus;
use App\Enums\ExclusionReason;
use App\Enums\PreEnrollmentFormStatus;
use App\Models\AcademicYear;
use App\Models\AdminUser;
use App\Models\Enrollment;
use App\Models\Family;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Guardian;
use App\Models\Kinder;
use App\Models\PreEnrollmentForm;
use App\Models\Student;
use App\Notifications\PreEnrollmentLateAddNotification;
use App\Services\PreEnrollment\CampaignCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LateAddControllerTest extends TestCase
{
    use RefreshDatabase;

    private function openCampaign(): array
    {
        Notification::fake();

        $kinder = Kinder::factory()->create();
        $admin = AdminUser::factory()->create(['kinder_id' => $kinder->id]);
        $grade = Grade::factory()->create(['order' => 0, 'is_final' => false]);
        $academicYear = AcademicYear::factory()->current()->create(['kinder_id' => $kinder->id]);
        $group = Group::factory()->create(['grade_id' => $grade->id, 'academic_year_id' => $academicYear->id]);

        // Baseline student, already in the pool when the campaign is created — the reinclude and
        // already-included tests need an existing form to act on.
        $baseFamily = Family::factory()->create(['kinder_id' => $kinder->id]);
        $baseStudent = Student::factory()->create(['family_id' => $baseFamily->id]);
        Enrollment::factory()->create([
            'student_id' => $baseStudent->id,
            'academic_year_id' => $academicYear->id,
            'group_id' => $group->id,
            'grade_id' => $grade->id,
        ]);

        $campaign = (new CampaignCreationService)->create($academicYear->id, $admin->id);

        Sanctum::actingAs($admin, ['*']);
        $this->postJson("/api/admin/pre-enrollment/campaigns/{$campaign->id}/open")->assertOk();

        return [$campaign->fresh(), $admin, $kinder, $grade, $academicYear, $group];
    }

    public function test_adds_a_student_who_was_never_in_the_original_pool(): void
    {
        [$campaign, $admin, $kinder, $grade, $academicYear, $group] = $this->openCampaign();

        // Created after the campaign, so CampaignCreationService never generated a form for them.
        $family = Family::factory()->create(['kinder_id' => $kinder->id]);
        Guardian::factory()->create(['family_id' => $family->id, 'email' => 'lateadd@example.test', 'status' => true]);
        $student = Student::factory()->create(['family_id' => $family->id]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'group_id' => $group->id,
            'grade_id' => $grade->id,
            'status' => EnrollmentStatus::Active,
        ]);

        $response = $this->postJson("/api/admin/pre-enrollment/campaigns/{$campaign->id}/students/late-add", [
            'studentId' => $student->id,
        ]);

        $response->assertCreated();

        $form = PreEnrollmentForm::where('campaign_id', $campaign->id)->where('student_id', $student->id)->firstOrFail();
        $this->assertFalse($form->is_excluded);
        $this->assertTrue($form->added_late);
        $this->assertNotNull($form->notified_at);

        $this->assertDatabaseHas('pre_enrollment_family_drafts', [
            'campaign_id' => $campaign->id,
            'family_id' => $family->id,
        ]);

        Notification::assertSentTo(
            new AnonymousNotifiable,
            PreEnrollmentLateAddNotification::class,
        );
    }

    public function test_reincludes_a_previously_excluded_student(): void
    {
        [$campaign, $admin] = $this->openCampaign();

        $form = PreEnrollmentForm::where('campaign_id', $campaign->id)->firstOrFail();
        $form->update([
            'is_excluded' => true,
            'exclusion_reason' => ExclusionReason::ConfirmedWithdrawal,
            'status' => PreEnrollmentFormStatus::Excluded,
        ]);

        $response = $this->postJson("/api/admin/pre-enrollment/campaigns/{$campaign->id}/students/late-add", [
            'studentId' => $form->student_id,
        ]);

        $response->assertCreated();

        $form->refresh();
        $this->assertFalse($form->is_excluded);
        $this->assertTrue($form->added_late);
        $this->assertNull($form->exclusion_reason);
    }

    public function test_rejected_when_student_already_included(): void
    {
        [$campaign, $admin] = $this->openCampaign();

        $form = PreEnrollmentForm::where('campaign_id', $campaign->id)->firstOrFail();

        $this->postJson("/api/admin/pre-enrollment/campaigns/{$campaign->id}/students/late-add", [
            'studentId' => $form->student_id,
        ])->assertStatus(422);
    }

    public function test_rejected_while_campaign_is_draft(): void
    {
        Notification::fake();

        $kinder = Kinder::factory()->create();
        $admin = AdminUser::factory()->create(['kinder_id' => $kinder->id]);
        $academicYear = AcademicYear::factory()->current()->create(['kinder_id' => $kinder->id]);
        $campaign = (new CampaignCreationService)->create($academicYear->id, $admin->id);

        $family = Family::factory()->create(['kinder_id' => $kinder->id]);
        $student = Student::factory()->create(['family_id' => $family->id]);

        Sanctum::actingAs($admin, ['*']);

        $this->postJson("/api/admin/pre-enrollment/campaigns/{$campaign->id}/students/late-add", [
            'studentId' => $student->id,
        ])->assertStatus(422);
    }
}
