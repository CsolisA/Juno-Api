<?php

namespace App\Http\Controllers\Admin\PreEnrollment;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\PreEnrollment\SelectionScreenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SelectionController extends Controller
{
    use ScopesCampaignToKinder;

    public function __construct(private readonly SelectionScreenService $selectionScreenService) {}

    public function show(Request $request, int $campaignId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $campaign = $this->campaignForAdmin($campaignId, $admin);

        return response()->json($this->selectionScreenService->build($campaign));
    }
}
