<?php

namespace App\Services\PreEnrollment;

use App\Models\PreEnrollmentCampaign;
use App\Models\PreEnrollmentForm;
use Illuminate\Support\Collection;

class SelectionScreenService
{
    public function __construct(private readonly CampaignCreationService $campaignCreationService) {}

    /**
     * @return array<string, mixed>
     */
    public function build(PreEnrollmentCampaign $campaign): array
    {
        $forms = PreEnrollmentForm::where('campaign_id', $campaign->id)
            ->with(['student', 'family', 'currentGrade', 'targetGrade'])
            ->get();

        $fullyBlockedFamilyIds = $this->campaignCreationService->fullyBlockedFamilyIds($campaign);

        $families = $forms
            ->groupBy('family_id')
            ->map(function (Collection $formsForFamily) use ($fullyBlockedFamilyIds) {
                /** @var PreEnrollmentForm $first */
                $first = $formsForFamily->first();
                $family = $first->family;

                return [
                    'familyId' => $family->id,
                    'familyName' => trim("{$family->last_name_one} {$family->last_name_two}"),
                    'fullyBlocked' => $fullyBlockedFamilyIds->contains($family->id),
                    'students' => $formsForFamily->map(fn (PreEnrollmentForm $form) => $this->formatStudent($form))->values(),
                ];
            })
            ->values();

        return [
            'campaign' => $this->formatCampaign($campaign),
            'families' => $families,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatStudent(PreEnrollmentForm $form): array
    {
        $isGraduating = $form->currentGrade->is_final;
        $student = $form->student;

        return [
            'formId' => $form->id,
            'studentId' => $student->id,
            'name' => trim("{$student->name} {$student->last_name} {$student->last_name_two}"),
            'currentGrade' => [
                'id' => $form->currentGrade->id,
                'name' => $form->currentGrade->name,
            ],
            'targetGrade' => $form->targetGrade ? [
                'id' => $form->targetGrade->id,
                'name' => $form->targetGrade->name,
            ] : null,
            'isGraduating' => $isGraduating,
            'isExcluded' => $form->is_excluded,
            'exclusionReason' => $isGraduating
                ? null
                : ($form->exclusion_reason?->value === 'other'
                    ? $form->exclusion_reason_detail
                    : $form->exclusion_reason?->label()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatCampaign(PreEnrollmentCampaign $campaign): array
    {
        return [
            'id' => $campaign->id,
            'name' => $campaign->name,
            'status' => $campaign->status->value,
            'academicYear' => [
                'id' => $campaign->academicYear->id,
                'year' => (string) $campaign->academicYear->year,
            ],
            'dueDate' => $campaign->due_date?->toDateString(),
            'enforceDueDate' => $campaign->enforce_due_date,
            'openedAt' => $campaign->opened_at?->toIso8601String(),
            'closedAt' => $campaign->closed_at?->toIso8601String(),
            'createdAt' => $campaign->created_at?->toIso8601String(),
        ];
    }
}
