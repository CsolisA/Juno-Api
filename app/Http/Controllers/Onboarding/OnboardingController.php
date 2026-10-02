<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\FamilyInvite;
use App\Services\Onboarding\FamilyOnboardingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public wizard behind a single-use invite link. The token travels in the `X-Invite-Token`
 * header (not the URL path) so it stays out of access logs; an unknown, expired, revoked or
 * already-used token all answer with the same 404 so nothing leaks about which one it was.
 */
class OnboardingController extends Controller
{
    private const MAX_DRAFT_BYTES = 200_000;

    public function __construct(private readonly FamilyOnboardingService $service) {}

    public function session(Request $request): JsonResponse
    {
        $invite = $this->usableInvite($request);

        return response()->json([
            'label' => $invite->label,
            'expiresAt' => $invite->expires_at->toIso8601String(),
            'revision' => $invite->revision,
            'draft' => $invite->draft_payload ?? (object) [],
            'kinder' => [
                'name' => $invite->kinder->name,
                'mainColor' => $invite->kinder->main_color,
                'secondColor' => $invite->kinder->second_color,
                'fontName' => $invite->kinder->font_name,
            ],
        ]);
    }

    /**
     * Autosave. The draft is replaced wholesale (the wizard owns its whole shape) and isn't
     * validated yet — validation happens on submit — but the revision guards against two tabs
     * silently overwriting each other.
     */
    public function draft(Request $request): JsonResponse
    {
        $invite = $this->usableInvite($request);

        $data = $request->validate([
            'revision' => ['required', 'integer'],
            'draft' => ['required', 'array'],
        ]);

        abort_if(strlen((string) json_encode($data['draft'])) > self::MAX_DRAFT_BYTES, 422, 'El borrador es demasiado grande.');

        if ($data['revision'] !== $invite->revision) {
            return response()->json([
                'message' => 'El borrador fue modificado en otra sesión.',
                'revision' => $invite->revision,
                'draft' => $invite->draft_payload ?? (object) [],
            ], 409);
        }

        $invite->update([
            'draft_payload' => $data['draft'],
            'revision' => $invite->revision + 1,
        ]);

        return response()->json([
            'revision' => $invite->revision,
            'savedAt' => now()->toIso8601String(),
        ]);
    }

    public function submit(Request $request): JsonResponse
    {
        $invite = $this->usableInvite($request);

        $data = $request->validate(
            FamilyOnboardingService::rules(),
            FamilyOnboardingService::messages(),
        );

        $family = $this->service->submit($invite, $data);

        return response()->json([
            'user' => $family->user,
            'students' => count($data['students']),
        ], 201);
    }

    private function usableInvite(Request $request): FamilyInvite
    {
        $invite = FamilyInvite::findByToken($request->header('X-Invite-Token'));

        abort_unless($invite && $invite->isUsable(), 404);

        return $invite->loadMissing('kinder');
    }
}
