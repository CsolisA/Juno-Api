<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Province;
use Illuminate\Http\JsonResponse;

class ProvinceCatalogController extends Controller
{
    public function index(): JsonResponse
    {
        $provinces = Province::orderBy('display_order')->get(['id', 'name']);

        return response()->json($provinces->map(fn (Province $province) => [
            'id' => $province->id,
            'name' => $province->name,
        ]));
    }
}
