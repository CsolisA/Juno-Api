<?php

namespace Tests\Feature\Family;

use App\Enums\PreEnrollmentCampaignStatus;
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
use Tests\TestCase;

class PreEnrollmentAutoCloseTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_auto_closes_once_every_family_has_submitted(): void
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

        Sanctum::actingAs($family, ['*']);

        foreach (PreEnrollmentForm::REQUIRED_DRAFT_PATHS as $i => $path) {
            $this->patchJson("/api/family/pre-enrollment/{$campaign->id}/students/{$student->id}", [
                'revision' => $i,
                'changes' => [$path => "value-{$i}"],
            ])->assertOk();
        }

        $motherFields = [];
        foreach (PreEnrollmentFamilyDraft::GUARDIAN_REQUIRED_FIELDS as $field) {
            $motherFields["guardians.mother.{$field}"] = "value-{$field}";
        }
        $this->patchJson("/api/family/pre-enrollment/{$campaign->id}/family", [
            'revision' => 0,
            'changes' => $motherFields,
        ])->assertOk();

        $this->assertSame(PreEnrollmentCampaignStatus::Open, $campaign->fresh()->status);

        $this->postJson("/api/family/pre-enrollment/{$campaign->id}/submit")->assertOk();

        $campaign->refresh();
        $this->assertSame(PreEnrollmentCampaignStatus::Closed, $campaign->status);
        $this->assertNotNull($campaign->closed_at);
    }
}
