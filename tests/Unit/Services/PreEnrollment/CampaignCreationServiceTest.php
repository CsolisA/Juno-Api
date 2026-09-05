<?php

namespace Tests\Unit\Services\PreEnrollment;

use App\Enums\ExclusionReason;
use App\Enums\PreEnrollmentFormStatus;
use App\Models\AcademicYear;
use App\Models\AdminUser;
use App\Models\Enrollment;
use App\Models\Family;
use App\Models\Grade;
use App\Models\Group;
use App\Models\Kinder;
use App\Models\PreEnrollmentCampaign;
use App\Models\PreEnrollmentForm;
use App\Models\Student;
use App\Services\PreEnrollment\CampaignCreationService;
use App\Services\PreEnrollment\Exceptions\DuplicateActiveCampaignException;
use App\Services\PreEnrollment\Exceptions\NoSourceAcademicYearException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignCreationServiceTest extends TestCase
{
    use RefreshDatabase;

    private CampaignCreationService $service;

    private Kinder $kinder;

    private AdminUser $admin;

    private AcademicYear $sourceYear;

    private AcademicYear $targetYear;

    /** @var array<string, Grade> */
    private array $grades;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new CampaignCreationService;
        $this->kinder = Kinder::factory()->create();
        $this->admin = AdminUser::factory()->create(['kinder_id' => $this->kinder->id]);
        $this->sourceYear = AcademicYear::factory()->current()->create(['kinder_id' => $this->kinder->id]);
        $this->targetYear = AcademicYear::factory()->create([
            'kinder_id' => $this->kinder->id,
            'year' => $this->sourceYear->year + 1,
            'start_date' => ($this->sourceYear->year + 1).'-02-01',
            'end_date' => ($this->sourceYear->year + 1).'-12-15',
        ]);

        $this->grades = [
            'maternal' => Grade::factory()->create(['name' => 'Maternal', 'order' => 0, 'is_final' => false]),
            'prekinder' => Grade::factory()->create(['name' => 'Prekinder', 'order' => 1, 'is_final' => false]),
            'kinder' => Grade::factory()->create(['name' => 'Kinder', 'order' => 2, 'is_final' => false]),
            'preparatoria' => Grade::factory()->create(['name' => 'Preparatoria', 'order' => 3, 'is_final' => true]),
        ];
    }

    private function enrollStudentInGrade(Grade $grade, ?Family $family = null): Enrollment
    {
        $group = Group::factory()->create([
            'grade_id' => $grade->id,
            'academic_year_id' => $this->sourceYear->id,
        ]);

        $student = Student::factory()->create([
            'family_id' => ($family ?? Family::factory()->create())->id,
        ]);

        return Enrollment::factory()->create([
            'student_id' => $student->id,
            'academic_year_id' => $this->sourceYear->id,
            'group_id' => $group->id,
            'grade_id' => $grade->id,
        ]);
    }

    public function test_grade_progression_advances_student_to_next_grade(): void
    {
        $enrollment = $this->enrollStudentInGrade($this->grades['prekinder']);

        $campaign = $this->service->create($this->targetYear->id, $this->admin->id);

        $form = PreEnrollmentForm::where('campaign_id', $campaign->id)
            ->where('student_id', $enrollment->student_id)
            ->firstOrFail();

        $this->assertSame($this->grades['kinder']->id, $form->target_grade_id);
        $this->assertFalse($form->is_excluded);
        $this->assertSame(PreEnrollmentFormStatus::Pending, $form->status);
        $this->assertSame($this->grades['prekinder']->id, $form->current_grade_id);
    }

    public function test_graduating_student_is_auto_excluded(): void
    {
        $enrollment = $this->enrollStudentInGrade($this->grades['preparatoria']);

        $campaign = $this->service->create($this->targetYear->id, $this->admin->id);

        $form = PreEnrollmentForm::where('campaign_id', $campaign->id)
            ->where('student_id', $enrollment->student_id)
            ->firstOrFail();

        $this->assertTrue($form->is_excluded);
        $this->assertSame(PreEnrollmentFormStatus::Excluded, $form->status);
        $this->assertSame(ExclusionReason::Graduating, $form->exclusion_reason);
        $this->assertNull($form->target_grade_id);
        $this->assertNull($form->excluded_by);
        $this->assertNotNull($form->excluded_at);
    }

    public function test_target_grade_override_fields_persist(): void
    {
        $form = PreEnrollmentForm::factory()->create([
            'target_grade_overridden' => true,
            'target_grade_overridden_by' => $this->admin->id,
            'target_grade_overridden_at' => now(),
        ]);

        $form->refresh();

        $this->assertTrue($form->target_grade_overridden);
        $this->assertSame($this->admin->id, $form->target_grade_overridden_by);
        $this->assertNotNull($form->target_grade_overridden_at);
    }

    public function test_family_with_all_students_graduating_is_fully_blocked(): void
    {
        $family = Family::factory()->create();
        $this->enrollStudentInGrade($this->grades['preparatoria'], $family);
        $this->enrollStudentInGrade($this->grades['preparatoria'], $family);

        $campaign = $this->service->create($this->targetYear->id, $this->admin->id);

        $this->assertTrue($this->service->fullyBlockedFamilyIds($campaign)->contains($family->id));
    }

    public function test_family_with_one_non_graduating_student_is_not_fully_blocked(): void
    {
        $family = Family::factory()->create();
        $this->enrollStudentInGrade($this->grades['preparatoria'], $family);
        $this->enrollStudentInGrade($this->grades['maternal'], $family);

        $campaign = $this->service->create($this->targetYear->id, $this->admin->id);

        $this->assertFalse($this->service->fullyBlockedFamilyIds($campaign)->contains($family->id));
    }

    public function test_duplicate_active_campaign_for_same_year_throws(): void
    {
        $this->service->create($this->targetYear->id, $this->admin->id);

        $this->expectException(DuplicateActiveCampaignException::class);

        $this->service->create($this->targetYear->id, $this->admin->id);
    }

    public function test_closed_campaign_does_not_block_new_campaign(): void
    {
        PreEnrollmentCampaign::factory()->closed()->create(['academic_year_id' => $this->targetYear->id]);

        $campaign = $this->service->create($this->targetYear->id, $this->admin->id);

        $this->assertNotNull($campaign->id);
    }

    public function test_missing_source_academic_year_throws(): void
    {
        $isolatedKinder = Kinder::factory()->create();
        $isolatedTargetYear = AcademicYear::factory()->create(['kinder_id' => $isolatedKinder->id]);

        $this->expectException(NoSourceAcademicYearException::class);

        $this->service->create($isolatedTargetYear->id, $this->admin->id);
    }

    public function test_duplicate_enrollment_for_same_student_does_not_create_duplicate_forms(): void
    {
        $family = Family::factory()->create();
        $student = Student::factory()->create(['family_id' => $family->id]);

        $groupA = Group::factory()->create([
            'grade_id' => $this->grades['prekinder']->id,
            'academic_year_id' => $this->sourceYear->id,
        ]);
        $groupB = Group::factory()->create([
            'grade_id' => $this->grades['prekinder']->id,
            'academic_year_id' => $this->sourceYear->id,
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'academic_year_id' => $this->sourceYear->id,
            'group_id' => $groupA->id,
            'grade_id' => $this->grades['prekinder']->id,
        ]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'academic_year_id' => $this->sourceYear->id,
            'group_id' => $groupB->id,
            'grade_id' => $this->grades['prekinder']->id,
        ]);

        $campaign = $this->service->create($this->targetYear->id, $this->admin->id);

        $this->assertSame(
            1,
            PreEnrollmentForm::where('campaign_id', $campaign->id)->where('student_id', $student->id)->count(),
        );
    }
}
