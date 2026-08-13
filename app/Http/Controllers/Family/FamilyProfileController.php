<?php

namespace App\Http\Controllers\Family;

use App\Http\Controllers\Controller;
use App\Http\Resources\FamilyResource;
use App\Models\Family;
use Illuminate\Http\Request;

class FamilyProfileController extends Controller
{
    public function show(Request $request): FamilyResource
    {
        return new FamilyResource($request->user());
    }

    public function update(Request $request): FamilyResource
    {
        $data = $request->validate([
            'lastNameOne' => ['sometimes', 'string', 'max:255'],
            'lastNameTwo' => ['sometimes', 'string', 'max:255'],
        ]);

        /** @var Family $family */
        $family = $request->user();

        $family->update([
            'last_name_one' => $data['lastNameOne'] ?? $family->last_name_one,
            'last_name_two' => $data['lastNameTwo'] ?? $family->last_name_two,
        ]);

        return new FamilyResource($family);
    }
}
