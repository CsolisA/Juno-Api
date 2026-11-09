<?php

namespace App\Http\Controllers\Family;

use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\PreEnrollmentFamilyDraft;
use App\Models\TermsVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Settings tab: the family can open the current terms and see which version they last accepted.
 */
class FamilyTermsController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        /** @var Family $family */
        $family = $request->user();

        return response()->json([
            'current' => TermsVersion::current()?->toPublicArray(),
            'accepted' => $this->lastAcceptance($family),
        ]);
    }

    /**
     * @return array{version: int, acceptedAt: string, pdfUrl: string}|null
     */
    private function lastAcceptance(Family $family): ?array
    {
        $draft = PreEnrollmentFamilyDraft::where('family_id', $family->id)
            ->whereNotNull('terms_accepted_at')
            ->orderByDesc('terms_accepted_at')
            ->first();

        $source = $draft ?? ($family->terms_accepted_at ? $family : null);

        if (! $source) {
            return null;
        }

        $version = (int) $source->terms_version;

        return [
            'version' => $version,
            'acceptedAt' => $source->terms_accepted_at->toIso8601String(),
            'pdfUrl' => url("/api/terms/{$version}/pdf"),
        ];
    }
}
