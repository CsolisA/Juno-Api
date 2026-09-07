<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StaffInviteController extends Controller
{
    /**
     * Completes an invited staff member's account setup: validates the token, sets their
     * password, and activates the account.
     */
    public function accept(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        $staff = AdminUser::where('invite_token', $data['token'])
            ->where('invite_expires_at', '>=', now())
            ->first();

        if (! $staff) {
            throw ValidationException::withMessages([
                'token' => 'Este enlace de invitación es inválido o ha expirado.',
            ]);
        }

        $staff->update([
            'password' => $data['password'],
            'invite_token' => null,
            'invite_expires_at' => null,
            'must_reset_password' => false,
            'status' => true,
        ]);

        return response()->json(['message' => 'Cuenta configurada correctamente.']);
    }
}
