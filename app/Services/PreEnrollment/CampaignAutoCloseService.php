<?php

namespace App\Services\PreEnrollment;

use App\Enums\PreEnrollmentCampaignStatus;
use App\Models\PreEnrollmentCampaign;
use App\Models\PreEnrollmentFamilyDraft;

/**
 * Auto-closes a campaign once every family in it has submitted (or already been approved).
 * Evaluated after a family submits — the only action that can flip the campaign from "not
 * everyone's done" to "everyone's done". Never reopens a campaign: reopening one family's
 * submission after close is an intentional director action (§ close), not an auto-close reversal.
 */
class CampaignAutoCloseService
{
    public function evaluate(PreEnrollmentCampaign $campaign): void
    {
        if ($campaign->status !== PreEnrollmentCampaignStatus::Open) {
            return;
        }

        $drafts = PreEnrollmentFamilyDraft::where('campaign_id', $campaign->id)->get();

        if ($drafts->isEmpty()) {
            return;
        }

        $allDone = $drafts->every(fn (PreEnrollmentFamilyDraft $draft) => $draft->submitted_at !== null);

        if ($allDone) {
            $campaign->update(['status' => PreEnrollmentCampaignStatus::Closed, 'closed_at' => now()]);
        }
    }
}
