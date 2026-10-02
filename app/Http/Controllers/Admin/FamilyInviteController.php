<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FamilyInviteStatus;
use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\FamilyInvite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FamilyInviteController extends Controller
{
    /**
     * Invites for the admin's kinder, newest first. The link itself is never listed: only the
     * hash is stored, so it's shown once on create/regenerate.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $invites = FamilyInvite::where('kinder_id', $admin->kinder_id)
            ->with(['creator:id,name', 'family:id,last_name_one,last_name_two,user'])
            ->latest('id')
            ->get();

        return response()->json($invites->map(fn (FamilyInvite $invite) => $this->format($invite)));
    }

    public function store(Request $request): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'expiresInDays' => ['nullable', 'integer', 'min:1', 'max:60'],
        ]);

        $token = FamilyInvite::generateToken();

        $invite = FamilyInvite::create([
            'kinder_id' => $admin->kinder_id,
            'token_hash' => FamilyInvite::hashToken($token),
            'label' => $data['label'],
            'phone' => $this->normalizePhone($data['phone'] ?? null),
            'status' => FamilyInviteStatus::Pending,
            'expires_at' => now()->addDays($data['expiresInDays'] ?? FamilyInvite::DEFAULT_VALID_DAYS),
            'created_by' => $admin->id,
        ]);

        return response()->json($this->formatWithLink($invite, $token, $admin), 201);
    }

    /**
     * Issues a fresh link for an invite whose link was lost, expired or revoked. The old link
     * stops working immediately. A submitted invite can't be reopened.
     */
    public function regenerate(Request $request, int $inviteId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $invite = $this->inviteForAdmin($inviteId, $admin);

        abort_if($invite->status === FamilyInviteStatus::Submitted, 422, 'Esta familia ya completó el registro.');

        $token = FamilyInvite::generateToken();

        $invite->update([
            'token_hash' => FamilyInvite::hashToken($token),
            'status' => FamilyInviteStatus::Pending,
            'expires_at' => now()->addDays(FamilyInvite::DEFAULT_VALID_DAYS),
        ]);

        return response()->json($this->formatWithLink($invite->fresh(), $token, $admin));
    }

    public function revoke(Request $request, int $inviteId): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $invite = $this->inviteForAdmin($inviteId, $admin);

        abort_if($invite->status === FamilyInviteStatus::Submitted, 422, 'Esta familia ya completó el registro.');

        $invite->update(['status' => FamilyInviteStatus::Revoked]);

        return response()->json($this->format($invite->fresh()));
    }

    /**
     * Cross-kinder ids 404 rather than 403, like the rest of the admin API.
     */
    private function inviteForAdmin(int $inviteId, AdminUser $admin): FamilyInvite
    {
        return FamilyInvite::where('kinder_id', $admin->kinder_id)->findOrFail($inviteId);
    }

    /**
     * WhatsApp wants digits only with the country code; a bare 8-digit Costa Rican number gets 506.
     */
    private function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', $phone ?? '');

        if ($digits === '') {
            return null;
        }

        return strlen($digits) === 8 ? '506'.$digits : $digits;
    }

    /**
     * @return array<string, mixed>
     */
    private function format(FamilyInvite $invite): array
    {
        return [
            'id' => $invite->id,
            'label' => $invite->label,
            'phone' => $invite->phone,
            'status' => $invite->displayStatus(),
            'expiresAt' => $invite->expires_at->toIso8601String(),
            'submittedAt' => $invite->submitted_at?->toIso8601String(),
            'createdAt' => $invite->created_at->toIso8601String(),
            'createdBy' => $invite->creator?->name,
            'family' => $invite->family ? [
                'id' => $invite->family->id,
                'name' => trim("{$invite->family->last_name_one} {$invite->family->last_name_two}"),
                'user' => $invite->family->user,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatWithLink(FamilyInvite $invite, string $token, AdminUser $admin): array
    {
        $url = FamilyInvite::urlFor($token);
        $kinderName = $admin->kinder?->name ?? config('app.name');
        $message = "Hola, te compartimos el enlace para registrar a tu familia en {$kinderName}: {$url}";

        return $this->format($invite) + [
            'url' => $url,
            'whatsappUrl' => 'https://wa.me/'.($invite->phone ?? '').'?text='.rawurlencode($message),
        ];
    }
}
