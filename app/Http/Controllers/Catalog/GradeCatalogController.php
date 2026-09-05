<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use Illuminate\Http\JsonResponse;

class GradeCatalogController extends Controller
{
    public function index(): JsonResponse
    {
        $grades = Grade::orderBy('order')->get(['id', 'name', 'order', 'is_final']);

        return response()->json($grades->map(fn (Grade $grade) => [
            'id' => $grade->id,
            'name' => $grade->name,
            'order' => $grade->order,
            'isFinal' => $grade->is_final,
        ]));
    }
}
