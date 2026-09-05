<?php

namespace Database\Factories;

use App\Models\Family;
use App\Models\PreEnrollmentCampaign;
use App\Models\PreEnrollmentFamilyDraft;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PreEnrollmentFamilyDraft>
 */
class PreEnrollmentFamilyDraftFactory extends Factory
{
    protected $model = PreEnrollmentFamilyDraft::class;

    public function definition(): array
    {
        return [
            'campaign_id' => PreEnrollmentCampaign::factory(),
            'family_id' => Family::factory(),
            'draft_payload' => [],
            'revision' => 0,
        ];
    }
}
