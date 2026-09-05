<?php

namespace App\Http\Controllers\Admin\PreEnrollment;

use App\Models\AdminUser;
use App\Models\PreEnrollmentCampaign;

trait ScopesCampaignToKinder
{
    /**
     * Fetch a campaign, 404ing if it doesn't belong to the given admin's kinder — admins from
     * one kinder must never be able to read or mutate another kinder's campaign by guessing ids.
     */
    private function campaignForAdmin(int $campaignId, AdminUser $admin): PreEnrollmentCampaign
    {
        return PreEnrollmentCampaign::whereHas(
            'academicYear',
            fn ($query) => $query->where('kinder_id', $admin->kinder_id),
        )->with('academicYear')->findOrFail($campaignId);
    }
}
