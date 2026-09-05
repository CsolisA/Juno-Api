<?php

namespace App\Http\Controllers\Family\PreEnrollment;

use App\Enums\PreEnrollmentCampaignStatus;
use App\Models\Family;
use App\Models\PreEnrollmentCampaign;
use App\Models\PreEnrollmentFamilyDraft;
use App\Models\PreEnrollmentForm;

trait ScopesPreEnrollmentToFamily
{
    /**
     * Fetch a campaign, 404ing unless this family has at least one form in it — a family must
     * never be able to read or mutate another family's campaign data by guessing ids.
     */
    private function campaignForFamily(int $campaignId, Family $family): PreEnrollmentCampaign
    {
        return PreEnrollmentCampaign::whereHas(
            'forms',
            fn ($query) => $query->where('family_id', $family->id),
        )->findOrFail($campaignId);
    }

    private function formForFamilyAndStudent(PreEnrollmentCampaign $campaign, Family $family, int $studentId): PreEnrollmentForm
    {
        return PreEnrollmentForm::where('campaign_id', $campaign->id)
            ->where('family_id', $family->id)
            ->where('student_id', $studentId)
            ->where('is_excluded', false)
            ->with('student')
            ->firstOrFail();
    }

    private function familyDraftFor(PreEnrollmentCampaign $campaign, Family $family): PreEnrollmentFamilyDraft
    {
        return PreEnrollmentFamilyDraft::firstOrCreate(
            ['campaign_id' => $campaign->id, 'family_id' => $family->id],
            ['draft_payload' => []],
        );
    }

    private function assertCampaignOpenForEditing(PreEnrollmentCampaign $campaign): void
    {
        abort_unless($campaign->status === PreEnrollmentCampaignStatus::Open, 422, 'Esta solicitud no está abierta.');

        abort_if(
            $campaign->enforce_due_date && $campaign->due_date && now()->gt($campaign->due_date->copy()->endOfDay()),
            422,
            'La fecha límite para esta solicitud ya pasó.',
        );
    }
}
