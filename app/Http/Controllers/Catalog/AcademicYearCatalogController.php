<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicYearCatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $years = AcademicYear::where('kinder_id', $admin->kinder_id)
            ->orderByDesc('year')
            ->get(['id', 'year']);

        return response()->json($years->map(fn (AcademicYear $year) => [
            'id' => $year->id,
            'year' => (string) $year->year,
        ]));
    }
}
