<?php

namespace Tests\Feature\Admin\PreEnrollment;

use App\Enums\GuardianRole;
use App\Models\AcademicYear;
use App\Models\AdminUser;
use App\Models\Enrollment;
use App\Models\Family;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Guardian;
use App\Models\Kinder;
use App\Models\PreEnrollmentFamilyDraft;
use App\Models\PreEnrollmentForm;
use App\Models\Student;
use App\Services\PreEnrollment\CampaignCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CampaignOpenPrefillTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_a_campaign_prefills_student_and_family_drafts(): void
    {
        Notification::fake();

        $kinder = Kinder::factory()->create();
        $admin = AdminUser::factory()->create(['kinder_id' => $kinder->id]);
        $grade = Grade::factory()->create(['order' => 0, 'is_final' => false]);
        $academicYear = AcademicYear::factory()->current()->create(['kinder_id' => $kinder->id]);
        $group = Group::factory()->create(['grade_id' => $grade->id, 'academic_year_id' => $academicYear->id]);

        $family = Family::factory()->create(['kinder_id' => $kinder->id]);
        Guardian::factory()->create([
            'family_id' => $family->id,
            'role' => GuardianRole::Mother,
            'mobile_phone' => '8888-1111',
        ]);
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
        $this->assertSame('O+', $form->draft_payload['student.bloodType']['value']);
        $this->assertSame('O+', $form->draft_payload['student.bloodType']['sourceValue']);

        $draft = PreEnrollmentFamilyDraft::where('campaign_id', $campaign->id)->where('family_id', $family->id)->firstOrFail();
        $this->assertSame('8888-1111', $draft->draft_payload['guardians.mother.mobilePhone']['value']);
    }
}
