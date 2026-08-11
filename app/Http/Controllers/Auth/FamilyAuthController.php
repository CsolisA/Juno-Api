<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Family;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class FamilyAuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'user' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $family = Family::where('user', $credentials['user'])->first();

        if (! $family || ! Hash::check($credentials['password'], $family->password)) {
            throw ValidationException::withMessages([
                'user' => __('auth.failed'),
            ]);
        }

        $token = $family->createToken('family-token')->plainTextToken;

        return response()->json([
            'family' => $family,
            'token' => $token,
        ]);
    }
}
