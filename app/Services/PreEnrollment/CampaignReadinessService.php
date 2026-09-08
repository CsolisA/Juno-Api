<?php

namespace App\Services\PreEnrollment;

use App\Models\Family;
use App\Models\PreEnrollmentCampaign;
use App\Models\PreEnrollmentForm;
use Illuminate\Support\Collection;

class CampaignReadinessService
{
    public function __construct(private readonly CampaignCreationService $campaignCreationService) {}

    /**
     * Families notification-ready for this campaign: any guardian with a status=true email,
     * falling back to family.user (populated since accounts are created manually). Shared by
     * the readiness check and the send-on-open notifier so they can't drift apart.
     *
     * @return Collection<int, Family>
     */
    public function readyFamilies(PreEnrollmentCampaign $campaign): Collection
    {
        return $this->familiesInCampaign($campaign)->filter(
            fn (Family $family) => $this->isReady($family),
        )->values();
    }

    /**
     * @return array<int, array{familyId: int, reason: string, students: array<int, string>}>
     */
    public function blockedFamilies(PreEnrollmentCampaign $campaign): array
    {
        $blocked = [];

        foreach ($this->formsGroupedByFamily($campaign) as $formsForFamily) {
            $family = $formsForFamily->first()->family;

            if ($this->isReady($family)) {
                continue;
            }

            $blocked[] = [
                'familyId' => $family->id,
                'reason' => 'no_email',
                'students' => $formsForFamily->map(fn (PreEnrollmentForm $form) => trim("{$form->student->name} {$form->student->last_name} {$form->student->last_name_two}"))->values()->all(),
            ];
        }

        return $blocked;
    }

    public function readyCount(PreEnrollmentCampaign $campaign): int
    {
        return $this->readyFamilies($campaign)->count();
    }

    /**
     * Every status=true guardian email for a family, falling back to family.user (per §9.2) —
     * shared by the launch and late-add notifiers so "who gets emailed" never drifts between them.
     *
     * @return Collection<int, string>
     */
    public function notifiableEmails(Family $family): Collection
    {
        $emails = $family->guardians
            ->where('status', true)
            ->pluck('email')
            ->filter()
            ->unique();

        if ($emails->isEmpty() && filter_var($family->user, FILTER_VALIDATE_EMAIL)) {
            $emails = collect([$family->user]);
        }

        return $emails->values();
    }

    private function isReady(Family $family): bool
    {
        $hasGuardianEmail = $family->guardians->contains(fn ($guardian) => $guardian->status && filled($guardian->email));
        $hasFallback = filled($family->user);

        return $hasGuardianEmail || $hasFallback;
    }

    /**
     * @return Collection<int, Collection<int, PreEnrollmentForm>>
     */
    private function formsGroupedByFamily(PreEnrollmentCampaign $campaign): Collection
    {
        $fullyBlockedFamilyIds = $this->campaignCreationService->fullyBlockedFamilyIds($campaign);

        return PreEnrollmentForm::where('campaign_id', $campaign->id)
            ->where('is_excluded', false)
            ->whereNotIn('family_id', $fullyBlockedFamilyIds)
            ->with(['student', 'family.guardians'])
            ->get()
            ->groupBy('family_id');
    }

    /**
     * @return Collection<int, Family>
     */
    private function familiesInCampaign(PreEnrollmentCampaign $campaign): Collection
    {
        return $this->formsGroupedByFamily($campaign)->map(fn ($formsForFamily) => $formsForFamily->first()->family);
    }
}
