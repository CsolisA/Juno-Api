<?php

namespace Tests\Feature\Admin\PreEnrollment;

use App\Enums\EnrollmentStatus;
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
use App\Models\Schedule;
use App\Models\Student;
use App\Services\PreEnrollment\CampaignCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubmissionControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Opens a campaign for a family with two children and submits both through the real
     * family-side flow, so PreEnrollmentFamilyDraft.submitted_at and both forms' statuses reflect
     * an actual "enviado" submission — not a hand-poked status.
     */
    private function submittedFamilyWithTwoChildren(): array
    {
        Notification::fake();

        $kinder = Kinder::factory()->create();
        $admin = AdminUser::factory()->create(['kinder_id' => $kinder->id]);
        $grade = Grade::factory()->create(['order' => 0, 'is_final' => false]);
        Grade::factory()->create(['order' => 1, 'is_final' => true]);
        $academicYear = AcademicYear::factory()->current()->create(['kinder_id' => $kinder->id]);
        $group = Group::factory()->create(['grade_id' => $grade->id, 'academic_year_id' => $academicYear->id]);

        $family = Family::factory()->create(['kinder_id' => $kinder->id]);
        $students = Student::factory()->count(2)->create(['family_id' => $family->id]);

        foreach ($students as $student) {
            Enrollment::factory()->create([
                'student_id' => $student->id,
                'academic_year_id' => $academicYear->id,
                'group_id' => $group->id,
                'grade_id' => $grade->id,
            ]);
        }

        $campaign = (new CampaignCreationService)->create($academicYear->id, $admin->id);

        Sanctum::actingAs($admin, ['*']);
        $this->postJson("/api/admin/pre-enrollment/campaigns/{$campaign->id}/open")->assertOk();

        Sanctum::actingAs($family, ['*']);

        $schedule = Schedule::factory()->create();
        $realValues = [
            'student.scheduleId' => $schedule->id,
            'student.transportType' => 'family',
        ];

        foreach ($students as $student) {
            foreach (PreEnrollmentForm::REQUIRED_DRAFT_PATHS as $i => $path) {
                $this->patchJson("/api/family/pre-enrollment/{$campaign->id}/students/{$student->id}", [
                    'revision' => $i,
                    'changes' => [$path => $realValues[$path] ?? "value-{$i}"],
                ])->assertOk();
            }
        }

        $motherFields = [];
        foreach (PreEnrollmentFamilyDraft::GUARDIAN_REQUIRED_FIELDS as $field) {
            $motherFields["guardians.mother.{$field}"] = "value-{$field}";
        }
        $this->patchJson("/api/family/pre-enrollment/{$campaign->id}/family", [
            'revision' => 0,
            'changes' => $motherFields,
        ])->assertOk();

        $this->postJson("/api/family/pre-enrollment/{$campaign->id}/submit")->assertOk();

        return [$campaign->fresh(), $family, $students, $admin];
    }

    public function test_approve_pushes_every_child_live_and_locks_the_submission(): void
    {
        [$campaign, $family, $students, $admin] = $this->submittedFamilyWithTwoChildren();

        Sanctum::actingAs($admin, ['*']);

        $response = $this->postJson("/api/admin/pre-enrollment/campaigns/{$campaign->id}/submissions/{$family->id}/approve");

        $response->assertOk();
        $response->assertJsonPath('status', 'approved');

        foreach ($students as $student) {
            $form = PreEnrollmentForm::where('campaign_id', $campaign->id)->where('student_id', $student->id)->firstOrFail();
            $this->assertSame(PreEnrollmentFormStatus::Applied, $form->status);
            $this->assertNotNull($form->projected_enrollment_id);
            $this->assertSame(EnrollmentStatus::Projected, Enrollment::findOrFail($form->projected_enrollment_id)->status);
        }

        $draft = PreEnrollmentFamilyDraft::where('campaign_id', $campaign->id)->where('family_id', $family->id)->firstOrFail();
        $this->assertNotNull($draft->approved_at);
        $this->assertSame($admin->id, $draft->approved_by);
    }

    public function test_edit_reopen_and_approve_are_all_rejected_once_approved(): void
    {
        [$campaign, $family, , $admin] = $this->submittedFamilyWithTwoChildren();

        Sanctum::actingAs($admin, ['*']);

        $this->postJson("/api/admin/pre-enrollment/campaigns/{$campaign->id}/submissions/{$family->id}/approve")->assertOk();

        $this->patchJson("/api/admin/pre-enrollment/campaigns/{$campaign->id}/submissions/{$family->id}", [
            'scope' => 'household',
            'changes' => ['guardians.mother.name' => 'Changed'],
        ])->assertStatus(422);

        $this->postJson("/api/admin/pre-enrollment/campaigns/{$campaign->id}/submissions/{$family->id}/reopen")->assertStatus(422);
        $this->postJson("/api/admin/pre-enrollment/campaigns/{$campaign->id}/submissions/{$family->id}/approve")->assertStatus(422);
    }

    public function test_reopen_sends_submitted_children_back_to_in_progress(): void
    {
        [$campaign, $family, $students, $admin] = $this->submittedFamilyWithTwoChildren();

        Sanctum::actingAs($admin, ['*']);

        $response = $this->postJson("/api/admin/pre-enrollment/campaigns/{$campaign->id}/submissions/{$family->id}/reopen", [
            'note' => 'Falta información.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'in_progress');

        foreach ($students as $student) {
            $form = PreEnrollmentForm::where('campaign_id', $campaign->id)->where('student_id', $student->id)->firstOrFail();
            $this->assertSame(PreEnrollmentFormStatus::InProgress, $form->status);
            $this->assertSame('Falta información.', $form->family_notes);
        }

        $draft = PreEnrollmentFamilyDraft::where('campaign_id', $campaign->id)->where('family_id', $family->id)->firstOrFail();
        $this->assertNull($draft->submitted_at);
        $this->assertNotNull($draft->reopened_at);
    }

    public function test_update_household_scope_writes_audit_row(): void
    {
        [$campaign, $family, , $admin] = $this->submittedFamilyWithTwoChildren();

        Sanctum::actingAs($admin, ['*']);

        $this->patchJson("/api/admin/pre-enrollment/campaigns/{$campaign->id}/submissions/{$family->id}", [
            'scope' => 'household',
            'changes' => ['guardians.mother.name' => 'Corrected Name'],
        ])->assertOk();

        $this->assertDatabaseHas('pre_enrollment_field_changes', [
            'field_path' => 'guardians.mother.name',
            'new_value' => 'Corrected Name',
            'actor_type' => 'admin',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_index_lists_family_with_aggregate_counts(): void
    {
        [$campaign, $family, , $admin] = $this->submittedFamilyWithTwoChildren();

        Sanctum::actingAs($admin, ['*']);

        $response = $this->getJson("/api/admin/pre-enrollment/campaigns/{$campaign->id}/submissions");

        $response->assertOk();
        $response->assertJsonPath('counts.total', 1);
        $response->assertJsonPath('counts.submitted', 1);
        $response->assertJsonPath('counts.approved', 0);
        $response->assertJsonPath('data.0.familyId', $family->id);
        $response->assertJsonPath('data.0.status', 'submitted');
    }
}
