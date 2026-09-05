<?php

namespace App\Services\PreEnrollment;

use App\Enums\EnrollmentStatus;
use App\Enums\ExclusionReason;
use App\Enums\PreEnrollmentCampaignStatus;
use App\Enums\PreEnrollmentFormStatus;
use App\Models\AcademicYear;
use App\Models\Enrollment;
use App\Models\PreEnrollmentCampaign;
use App\Models\PreEnrollmentForm;
use App\Services\PreEnrollment\Exceptions\DuplicateActiveCampaignException;
use App\Services\PreEnrollment\Exceptions\NoSourceAcademicYearException;
use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CampaignCreationService
{
    /**
     * @param  int|null  $sourceAcademicYearId  Overrides the "current year" resolution — mainly
     *                                          so tests can pin a specific source year without
     *                                          mocking now().
     */
    public function __construct(private readonly ?int $sourceAcademicYearId = null) {}

    /**
     * Create a draft campaign targeting $academicYearId and generate one PreEnrollmentForm per
     * currently-active enrollment in the source (current) academic year, applying grade
     * progression and graduation auto-exclusion. Pre-fill of draft_payload is intentionally not
     * done here — that's a separate, later concern.
     *
     * @throws DuplicateActiveCampaignException if a draft/open campaign already exists for
     *                                          this target academic year.
     * @throws NoSourceAcademicYearException if a source (current) academic year can't be
     *                                       resolved for the target's kinder.
     */
    public function create(
        int $academicYearId,
        int $createdByAdminUserId,
        ?string $name = null,
        ?CarbonInterface $dueDate = null,
        bool $enforceDueDate = false,
        ?string $notes = null,
    ): PreEnrollmentCampaign {
        /** @var AcademicYear $targetYear */
        $targetYear = AcademicYear::findOrFail($academicYearId);

        $sourceYear = $this->sourceAcademicYearId !== null
            ? AcademicYear::findOrFail($this->sourceAcademicYearId)
            : AcademicYear::current($targetYear->kinder_id);

        if ($sourceYear === null) {
            throw NoSourceAcademicYearException::forKinder($targetYear->kinder_id);
        }

        return DB::transaction(function () use ($targetYear, $sourceYear, $createdByAdminUserId, $name, $dueDate, $enforceDueDate, $notes) {
            $hasActiveCampaign = PreEnrollmentCampaign::where('academic_year_id', $targetYear->id)
                ->whereIn('status', [PreEnrollmentCampaignStatus::Draft, PreEnrollmentCampaignStatus::Open])
                ->lockForUpdate()
                ->exists();

            if ($hasActiveCampaign) {
                throw DuplicateActiveCampaignException::forAcademicYear($targetYear->id);
            }

            try {
                $campaign = PreEnrollmentCampaign::create([
                    'academic_year_id' => $targetYear->id,
                    'name' => $name ?? "Pre-Matrícula {$targetYear->year}",
                    'status' => PreEnrollmentCampaignStatus::Draft,
                    'due_date' => $dueDate,
                    'enforce_due_date' => $enforceDueDate,
                    'created_by' => $createdByAdminUserId,
                    'notes' => $notes,
                ]);
            } catch (QueryException $exception) {
                if (str_contains($exception->getMessage(), 'pre_enrollment_campaigns_active_year_unique')) {
                    throw DuplicateActiveCampaignException::forAcademicYear($targetYear->id);
                }

                throw $exception;
            }

            $enrollments = Enrollment::where('academic_year_id', $sourceYear->id)
                ->where('status', EnrollmentStatus::Active)
                ->with(['student', 'grade'])
                ->get();

            foreach ($enrollments as $enrollment) {
                $progression = $this->resolveProgression($enrollment);

                PreEnrollmentForm::firstOrCreate(
                    ['campaign_id' => $campaign->id, 'student_id' => $enrollment->student_id],
                    [
                        'family_id' => $enrollment->student->family_id,
                        'current_grade_id' => $enrollment->grade_id,
                        'current_group_id' => $enrollment->group_id,
                        'target_grade_id' => $progression->targetGrade?->id,
                        'status' => $progression->isGraduating ? PreEnrollmentFormStatus::Excluded : PreEnrollmentFormStatus::Pending,
                        'is_excluded' => $progression->isGraduating,
                        'exclusion_reason' => $progression->isGraduating ? ExclusionReason::Graduating : null,
                        'excluded_at' => $progression->isGraduating ? now() : null,
                        'draft_payload' => [],
                    ],
                );
            }

            return $campaign;
        });
    }

    /**
     * Pure progression logic for a single enrollment — no side effects, no DB writes.
     */
    public function resolveProgression(Enrollment $currentEnrollment): ProgressionResult
    {
        $currentGrade = $currentEnrollment->grade;

        if ($currentGrade->is_final) {
            return new ProgressionResult(targetGrade: null, isGraduating: true);
        }

        return new ProgressionResult(targetGrade: $currentGrade->nextGrade(), isGraduating: false);
    }

    /**
     * Family ids where every one of that family's forms in this campaign is excluded — reason
     * agnostic on purpose, so it stays correct once manual exclusion exists alongside graduation.
     *
     * @return Collection<int, int>
     */
    public function fullyBlockedFamilyIds(PreEnrollmentCampaign $campaign): Collection
    {
        return PreEnrollmentForm::where('campaign_id', $campaign->id)
            ->select('family_id')
            ->groupBy('family_id')
            ->havingRaw('SUM(CASE WHEN is_excluded THEN 0 ELSE 1 END) = 0')
            ->pluck('family_id');
    }
}
