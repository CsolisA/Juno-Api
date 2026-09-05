<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Canton;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CantonCatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'provinceId' => ['required', 'integer', 'exists:provinces,id'],
        ]);

        $cantons = Canton::where('province_id', $data['provinceId'])
            ->orderBy('display_order')
            ->get(['id', 'name']);

        return response()->json($cantons->map(fn (Canton $canton) => [
            'id' => $canton->id,
            'name' => $canton->name,
        ]));
    }
}
