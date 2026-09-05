<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Small reference lists with no DB table of their own — free-text fields on guardians/authorized
 * contacts (relationship, nationality, education level, marital status) and students
 * (blood type) still get a suggested dropdown list rather than an unconstrained input.
 */
class StaticCatalogController extends Controller
{
    public function relationships(): JsonResponse
    {
        return response()->json($this->list([
            'abuelo', 'abuela', 'tío', 'tía', 'hermano', 'hermano_mayor', 'padrastro', 'madrastra',
            'niñera', 'otro',
        ]));
    }

    public function nationalities(): JsonResponse
    {
        return response()->json($this->list([
            'Costarricense', 'Nicaragüense', 'Venezolana', 'Colombiana', 'Estadounidense', 'Otra',
        ]));
    }

    public function educationLevels(): JsonResponse
    {
        return response()->json($this->list([
            'primaria', 'secundaria', 'tecnico', 'universidad', 'posgrado',
        ]));
    }

    public function maritalStatuses(): JsonResponse
    {
        return response()->json($this->list([
            'soltero', 'casado', 'union_libre', 'divorciado', 'viudo',
        ]));
    }

    public function bloodTypes(): JsonResponse
    {
        return response()->json($this->list(['O+', 'O-', 'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-']));
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, array{value: string, label: string}>
     */
    private function list(array $values): array
    {
        return array_map(fn (string $value) => ['value' => $value, 'label' => $value], $values);
    }
}
