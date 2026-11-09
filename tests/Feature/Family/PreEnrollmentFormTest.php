<?php

namespace Tests\Feature\Family;

use App\Enums\ActorType;
use App\Enums\PreEnrollmentCampaignStatus;
use App\Enums\PreEnrollmentFormStatus;
use App\Models\AcademicYear;
use App\Models\AdminUser;
use App\Models\Enrollment;
use App\Models\Family;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Kinder;
use App\Models\PreEnrollmentFamilyDraft;
use App\Models\PreEnrollmentForm;
use App\Models\Student;
use App\Services\PreEnrollment\CampaignCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AcceptsTerms;
use Tests\TestCase;

class PreEnrollmentFormTest extends TestCase
{
    use AcceptsTerms;
    use RefreshDatabase;

    private function openCampaignWithOneStudent(): array
    {
        Notification::fake();

        $kinder = Kinder::factory()->create();
        $admin = AdminUser::factory()->create(['kinder_id' => $kinder->id]);
        $grade = Grade::factory()->create(['order' => 0, 'is_final' => false]);
        $academicYear = AcademicYear::factory()->current()->create(['kinder_id' => $kinder->id]);
        $group = Group::factory()->create(['grade_id' => $grade->id, 'academic_year_id' => $academicYear->id]);

        $family = Family::factory()->create(['kinder_id' => $kinder->id]);
        $student = Student::factory()->create(['family_id' => $family->id]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'group_id' => $group->id,
            'grade_id' => $grade->id,
        ]);

        $campaign = (new CampaignCreationService)->create($academicYear->id, $admin->id);

        Sanctum::actingAs($admin, ['*']);
        $this->postJson("/api/admin/pre-enrollment/campaigns/{$campaign->id}/open")->assertOk();

        return [$campaign, $family, $student];
    }

    public function test_family_can_fetch_bundle_and_autosave_then_submit(): void
    {
        [$campaign, $family, $student] = $this->openCampaignWithOneStudent();

        Sanctum::actingAs($family, ['*']);

        $bundle = $this->getJson("/api/family/pre-enrollment/{$campaign->id}")->assertOk()->json();
        $this->assertSame(0, $bundle['household']['revision']);
        $this->assertCount(1, $bundle['students']);

        $form = PreEnrollmentForm::where('campaign_id', $campaign->id)->where('student_id', $student->id)->firstOrFail();

        foreach (PreEnrollmentForm::REQUIRED_DRAFT_PATHS as $i => $path) {
            $response = $this->patchJson("/api/family/pre-enrollment/{$campaign->id}/students/{$student->id}", [
                'revision' => $i,
                'changes' => [$path => "value-{$i}"],
            ])->assertOk();
        }

        $form->refresh();
        $this->assertSame(100, $form->completion_percent);
        $this->assertSame(PreEnrollmentFormStatus::InProgress, $form->status);

        $motherFields = [];
        foreach (PreEnrollmentFamilyDraft::GUARDIAN_REQUIRED_FIELDS as $field) {
            $motherFields["guardians.mother.{$field}"] = "value-{$field}";
        }
        $this->patchJson("/api/family/pre-enrollment/{$campaign->id}/family", [
            'revision' => 0,
            'changes' => $motherFields,
        ])->assertOk();

        $submit = $this->postJson("/api/family/pre-enrollment/{$campaign->id}/submit", $this->termsPayload())->assertOk();

        $form->refresh();
        $this->assertSame(PreEnrollmentFormStatus::Submitted, $form->status);
        $this->assertNotNull($form->submitted_at);
        $this->assertNotNull($form->submitted_snapshot);

        $draft = PreEnrollmentFamilyDraft::where('campaign_id', $campaign->id)->where('family_id', $family->id)->firstOrFail();
        $this->assertNotNull($draft->terms_accepted_at);
        $this->assertSame($this->currentTerms()->version, (int) $draft->terms_version);

        $this->assertDatabaseHas('pre_enrollment_field_changes', [
            'form_id' => $form->id,
            'actor_type' => ActorType::Family->value,
            'actor_id' => $family->id,
        ]);
    }

    public function test_submit_requires_accepting_the_terms(): void
    {
        [$campaign, $family] = $this->openCampaignWithOneStudent();

        Sanctum::actingAs($family, ['*']);

        $this->currentTerms();

        $url = "/api/family/pre-enrollment/{$campaign->id}/submit";

        $this->postJson($url)->assertStatus(422)->assertJsonValidationErrors(['termsAccepted', 'termsVersion']);
        $this->postJson($url, $this->termsPayload(['termsAccepted' => false]))->assertStatus(422)->assertJsonValidationErrors('termsAccepted');
        $this->postJson($url, $this->termsPayload(['termsVersion' => 999]))->assertStatus(422)->assertJsonValidationErrors('termsVersion');

        $this->assertNull(PreEnrollmentFamilyDraft::where('family_id', $family->id)->first()?->terms_accepted_at);
    }

    public function test_submit_is_503_while_no_terms_have_been_uploaded(): void
    {
        [$campaign, $family] = $this->openCampaignWithOneStudent();

        Sanctum::actingAs($family, ['*']);

        $this->postJson("/api/family/pre-enrollment/{$campaign->id}/submit", ['termsAccepted' => true, 'termsVersion' => 1])
            ->assertStatus(503);
    }

    public function test_submit_rejects_incomplete_forms(): void
    {
        [$campaign, $family, $student] = $this->openCampaignWithOneStudent();

        Sanctum::actingAs($family, ['*']);

        $this->postJson("/api/family/pre-enrollment/{$campaign->id}/submit", $this->termsPayload())->assertStatus(422);
    }

    public function test_autosave_rejects_stale_revision_with_409(): void
    {
        [$campaign, $family, $student] = $this->openCampaignWithOneStudent();

        Sanctum::actingAs($family, ['*']);

        $this->patchJson("/api/family/pre-enrollment/{$campaign->id}/students/{$student->id}", [
            'revision' => 0,
            'changes' => ['student.bloodType' => 'O+'],
        ])->assertOk();

        $this->patchJson("/api/family/pre-enrollment/{$campaign->id}/students/{$student->id}", [
            'revision' => 0,
            'changes' => ['student.bloodType' => 'A+'],
        ])->assertStatus(409);
    }

    public function test_family_cannot_access_another_familys_campaign(): void
    {
        [$campaign] = $this->openCampaignWithOneStudent();

        $otherFamily = Family::factory()->create();
        Sanctum::actingAs($otherFamily, ['*']);

        $this->getJson("/api/family/pre-enrollment/{$campaign->id}")->assertStatus(404);
    }

    public function test_autosave_rejected_when_campaign_not_open(): void
    {
        [$campaign, $family, $student] = $this->openCampaignWithOneStudent();

        $campaign->update(['status' => PreEnrollmentCampaignStatus::Closed]);

        Sanctum::actingAs($family, ['*']);

        $this->patchJson("/api/family/pre-enrollment/{$campaign->id}/students/{$student->id}", [
            'revision' => 0,
            'changes' => ['student.bloodType' => 'O+'],
        ])->assertStatus(422);
    }
}
