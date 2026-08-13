<?php

namespace App\Http\Controllers\Family;

use App\Enums\GuardianRole;
use App\Http\Controllers\Controller;
use App\Models\Family;
use App\Models\FamilyPasswordReset;
use App\Notifications\FamilyPasswordResetNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FamilyPasswordController extends Controller
{
    /**
     * Send a reset link to the family's mother/father guardian email addresses.
     */
    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user' => ['required', 'string'],
        ]);

        $family = Family::where('user', $data['user'])->first();

        if ($family) {
            FamilyPasswordReset::where('family_id', $family->id)->delete();

            $token = Str::random(64);

            FamilyPasswordReset::create([
                'family_id' => $family->id,
                'token' => $token,
                'expires_at' => now()->addHour(),
            ]);

            $emails = $family->guardians()
                ->whereIn('role', [GuardianRole::Mother->value, GuardianRole::Father->value])
                ->pluck('email')
                ->filter()
                ->unique();

            foreach ($emails as $email) {
                Notification::route('mail', $email)
                    ->notify(new FamilyPasswordResetNotification($token, $family));
            }
        }

        // Always return a generic response so we don't leak whether a `user` exists.
        return response()->json([
            'message' => 'If the account exists, a password reset link has been sent.',
        ]);
    }

    /**
     * Complete a password reset using the token from the email/link.
     */
    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'newPassword' => ['required', 'string', 'min:8'],
        ]);

        $reset = FamilyPasswordReset::where('token', $data['token'])
            ->where('expires_at', '>=', now())
            ->first();

        if (! $reset) {
            throw ValidationException::withMessages([
                'token' => 'This password reset link is invalid or has expired.',
            ]);
        }

        $reset->family->update(['password' => $data['newPassword']]);
        $reset->delete();

        return response()->json(['message' => 'Password updated.']);
    }

    /**
     * Change password for the logged-in family, confirming the current password.
     */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'currentPassword' => ['required', 'string'],
            'newPassword' => ['required', 'string', 'min:8'],
        ]);

        /** @var Family $family */
        $family = $request->user();

        if (! Hash::check($data['currentPassword'], $family->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => __('auth.password'),
            ]);
        }

        $family->update(['password' => $data['newPassword']]);

        return response()->json(['message' => 'Password updated.']);
    }
}
