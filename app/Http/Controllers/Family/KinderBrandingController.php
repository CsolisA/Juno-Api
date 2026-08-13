<?php

namespace App\Http\Controllers\Family;

use App\Http\Controllers\Controller;
use App\Models\Kinder;
use Illuminate\Http\JsonResponse;

class KinderBrandingController extends Controller
{
    public function show(): JsonResponse
    {
        $kinder = Kinder::first();

        abort_unless($kinder, 404);

        return response()->json([
            'name' => $kinder->name,
            'mainColor' => $kinder->main_color,
            'secondColor' => $kinder->second_color,
            'fontName' => $kinder->font_name,
        ]);
    }
}
