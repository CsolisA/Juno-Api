<?php

namespace App\Services\PreEnrollment;

use App\Enums\ExclusionReason;
use App\Enums\PreEnrollmentCampaignStatus;
use App\Enums\PreEnrollmentFormStatus;
use App\Models\PreEnrollmentCampaign;
use App\Models\PreEnrollmentForm;
use Illuminate\Support\Collection;

class CampaignStatsService
{
    public function __construct(private readonly CampaignCreationService $campaignCreationService) {}

    /**
     * @return array<string, mixed>
     */
    public function build(PreEnrollmentCampaign $campaign): array
    {
        $forms = PreEnrollmentForm::where('campaign_id', $campaign->id)
            ->with('targetGrade')
            ->get();

        $excluded = $forms->where('is_excluded', true);
        $included = $forms->where('is_excluded', false);

        $doneStatuses = [
            PreEnrollmentFormStatus::Submitted,
            PreEnrollmentFormStatus::Approved,
            PreEnrollmentFormStatus::Applied,
        ];

        $byStatus = [
            'pending' => $included->where('status', PreEnrollmentFormStatus::Pending)->count(),
            'inProgress' => $included->where('status', PreEnrollmentFormStatus::InProgress)->count(),
            'submitted' => $included->where('status', PreEnrollmentFormStatus::Submitted)->count(),
            'approved' => $included->whereIn('status', [PreEnrollmentFormStatus::Approved, PreEnrollmentFormStatus::Applied])->count(),
            'notSubmitted' => $included->where('status', PreEnrollmentFormStatus::NotSubmitted)->count(),
        ];

        $byTargetGrade = $included
            ->whereNotNull('target_grade_id')
            ->groupBy('target_grade_id')
            ->map(function (Collection $group) use ($doneStatuses) {
                /** @var PreEnrollmentForm $first */
                $first = $group->first();

                return [
                    'gradeId' => $first->target_grade_id,
                    'name' => $first->targetGrade?->name,
                    'pending' => $group->where('status', PreEnrollmentFormStatus::Pending)->count(),
                    'inProgress' => $group->where('status', PreEnrollmentFormStatus::InProgress)->count(),
                    'submitted' => $group->whereIn('status', $doneStatuses)->count(),
                ];
            })
            ->values();

        $fullyBlockedFamilyIds = $this->campaignCreationService->fullyBlockedFamilyIds($campaign);

        $notStarted = 0;
        $inProgress = 0;
        $completed = 0;

        foreach ($forms->whereNotIn('family_id', $fullyBlockedFamilyIds)->groupBy('family_id') as $formsForFamily) {
            $nonExcluded = $formsForFamily->where('is_excluded', false);
            if ($nonExcluded->isEmpty()) {
                continue;
            }

            if ($nonExcluded->every(fn (PreEnrollmentForm $form) => in_array($form->status, $doneStatuses, true))) {
                $completed++;
            } elseif ($nonExcluded->every(fn (PreEnrollmentForm $form) => $form->status === PreEnrollmentFormStatus::Pending)) {
                $notStarted++;
            } else {
                $inProgress++;
            }
        }

        $canClose = $campaign->status === PreEnrollmentCampaignStatus::Open
            && $included->contains(fn (PreEnrollmentForm $form) => in_array($form->status, [
                PreEnrollmentFormStatus::Pending,
                PreEnrollmentFormStatus::InProgress,
            ], true));

        return [
            'totals' => [
                'students' => $forms->count(),
                'included' => $included->count(),
                'excluded' => $excluded->count(),
            ],
            'byStatus' => $byStatus,
            'byTargetGrade' => $byTargetGrade,
            'families' => [
                'total' => $notStarted + $inProgress + $completed,
                'notStarted' => $notStarted,
                'inProgress' => $inProgress,
                'completed' => $completed,
            ],
            'graduating' => $excluded->where('exclusion_reason', ExclusionReason::Graduating)->count(),
            'dueDate' => $campaign->due_date?->toDateString(),
            'canClose' => $canClose,
        ];
    }
}
