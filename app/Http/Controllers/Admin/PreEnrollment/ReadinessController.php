<?php

namespace App\Http\Controllers\Admin\PreEnrollment;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\PreEnrollment\CampaignReadinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReadinessController extends Controller
{
    use ScopesCampaignToKinder;

    public function __construct(private readonly CampaignReadinessService $campaignReadinessService) {}

    /**
     * A family is notification-ready per §9.2 of the plan: any guardian with a status=true email,
     * falling back to family.user (always populated, since accounts are created manually before a
     * student can even be enrolled). In practice this means `blocked` stays empty until a family's
     * login `user` field is ever allowed to be non-email — this endpoint exists so that stops being
     * a silent assumption once it changes.
     */
    public function show(Request $request, int $campaignId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $campaign = $this->campaignForAdmin($campaignId, $admin);

        return response()->json([
            'ready' => $this->campaignReadinessService->readyCount($campaign),
            'blocked' => $this->campaignReadinessService->blockedFamilies($campaign),
        ]);
    }
}
