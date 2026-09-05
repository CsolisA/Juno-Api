<?php

namespace Tests\Unit\Services\PreEnrollment;

use App\Models\AcademicYear;
use App\Models\AdminUser;
use App\Models\Enrollment;
use App\Models\Family;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Guardian;
use App\Models\Kinder;
use App\Models\Student;
use App\Services\PreEnrollment\CampaignCreationService;
use App\Services\PreEnrollment\CampaignReadinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    private CampaignReadinessService $service;

    private CampaignCreationService $creationService;

    private AcademicYear $sourceYear;

    private AcademicYear $targetYear;

    private Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creationService = new CampaignCreationService;
        $this->service = new CampaignReadinessService($this->creationService);

        $kinder = Kinder::factory()->create();
        $this->sourceYear = AcademicYear::factory()->current()->create(['kinder_id' => $kinder->id]);
        $this->targetYear = AcademicYear::factory()->create([
            'kinder_id' => $kinder->id,
            'year' => $this->sourceYear->year + 1,
            'start_date' => ($this->sourceYear->year + 1).'-02-01',
            'end_date' => ($this->sourceYear->year + 1).'-12-15',
        ]);
        $this->grade = Grade::factory()->create(['order' => 0, 'is_final' => false]);
        Grade::factory()->create(['order' => 1, 'is_final' => true]);
    }

    private function enrollStudentForFamily(Family $family): Enrollment
    {
        $group = Group::factory()->create(['grade_id' => $this->grade->id, 'academic_year_id' => $this->sourceYear->id]);
        $student = Student::factory()->create(['family_id' => $family->id]);

        return Enrollment::factory()->create([
            'student_id' => $student->id,
            'academic_year_id' => $this->sourceYear->id,
            'group_id' => $group->id,
            'grade_id' => $this->grade->id,
        ]);
    }

    public function test_family_with_status_true_guardian_email_is_ready(): void
    {
        $admin = AdminUser::factory()->create();
        $family = Family::factory()->create();
        Guardian::factory()->create(['family_id' => $family->id, 'email' => 'mom@example.test', 'status' => true]);
        $this->enrollStudentForFamily($family);

        $campaign = $this->creationService->create($this->targetYear->id, $admin->id);

        $this->assertTrue($this->service->readyFamilies($campaign)->contains('id', $family->id));
        $this->assertSame([], $this->service->blockedFamilies($campaign));
    }

    public function test_family_with_only_inactive_guardian_email_falls_back_to_user_and_is_blocked_if_user_missing(): void
    {
        $admin = AdminUser::factory()->create();
        $family = Family::factory()->create(['user' => '']);
        Guardian::factory()->create(['family_id' => $family->id, 'email' => 'dad@example.test', 'status' => false]);
        $this->enrollStudentForFamily($family);

        $campaign = $this->creationService->create($this->targetYear->id, $admin->id);

        $this->assertFalse($this->service->readyFamilies($campaign)->contains('id', $family->id));
        $blocked = $this->service->blockedFamilies($campaign);
        $this->assertCount(1, $blocked);
        $this->assertSame($family->id, $blocked[0]['familyId']);
        $this->assertSame('no_email', $blocked[0]['reason']);
    }

    public function test_family_with_no_guardian_email_falls_back_to_user(): void
    {
        $admin = AdminUser::factory()->create();
        $family = Family::factory()->create(['user' => 'someuser']);
        $this->enrollStudentForFamily($family);

        $campaign = $this->creationService->create($this->targetYear->id, $admin->id);

        $this->assertTrue($this->service->readyFamilies($campaign)->contains('id', $family->id));
    }

    public function test_fully_excluded_family_is_neither_ready_nor_blocked(): void
    {
        $admin = AdminUser::factory()->create();
        $family = Family::factory()->create();
        $finalGrade = Grade::where('is_final', true)->first();
        $group = Group::factory()->create(['grade_id' => $finalGrade->id, 'academic_year_id' => $this->sourceYear->id]);
        $student = Student::factory()->create(['family_id' => $family->id]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'academic_year_id' => $this->sourceYear->id,
            'group_id' => $group->id,
            'grade_id' => $finalGrade->id,
        ]);

        $campaign = $this->creationService->create($this->targetYear->id, $admin->id);

        $this->assertFalse($this->service->readyFamilies($campaign)->contains('id', $family->id));
        $this->assertSame(0, $this->service->readyCount($campaign));
        $this->assertSame([], $this->service->blockedFamilies($campaign));
    }
}
