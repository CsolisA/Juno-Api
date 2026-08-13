<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AdminAuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $adminUser = AdminUser::where('email', $credentials['email'])->first();

        if (! $adminUser || ! Hash::check($credentials['password'], $adminUser->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (! $adminUser->status) {
            throw ValidationException::withMessages([
                'email' => __('This account is inactive.'),
            ]);
        }

        $token = $adminUser->createToken('admin-user-token')->plainTextToken;

        return response()->json([
            'admin_user' => $adminUser,
            'token' => $token,
        ]);
    }

    public function me(Request $request)
    {
        abort_unless($request->user() instanceof AdminUser, 403);

        return response()->json(['admin_user' => $request->user()]);
    }
}
