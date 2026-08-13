<?php

namespace App\Http\Controllers\Family;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthorizedResource;
use App\Models\Authorized;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FamilyAuthorizedContactController extends Controller
{
    public function index(Request $request, Student $student): AnonymousResourceCollection
    {
        $this->authorizeStudent($request, $student);

        return AuthorizedResource::collection($student->authorizedPersons);
    }

    public function store(Request $request, Student $student): AuthorizedResource
    {
        $this->authorizeStudent($request, $student);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'lastName' => ['required', 'string', 'max:255'],
            'lastNameTwo' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'related' => ['required', 'string', 'max:255'],
            'pickUp' => ['sometimes', 'boolean'],
            'livesWithChild' => ['sometimes', 'boolean'],
            'ocupation' => ['nullable', 'string', 'max:255'],
            'communication' => ['sometimes', 'boolean'],
        ]);

        $authorized = Authorized::create([
            'name' => $data['name'],
            'last_name' => $data['lastName'],
            'last_name_two' => $data['lastNameTwo'],
            'phone' => $data['phone'],
            'relationship' => $data['related'],
            'pick_up' => $request->boolean('pickUp'),
            'lives_with_child' => $request->boolean('livesWithChild'),
            'occupation' => $data['ocupation'] ?? null,
            'communication' => $request->boolean('communication'),
            'status' => true,
        ]);

        $authorized->students()->attach($student->id);

        return new AuthorizedResource($authorized);
    }

    public function destroy(Request $request, Student $student, Authorized $authorized): AuthorizedResource
    {
        $this->authorizeStudent($request, $student);

        abort_unless(
            $authorized->students()->where('students.id', $student->id)->exists(),
            404,
        );

        $authorized->update(['status' => false]);

        return new AuthorizedResource($authorized);
    }

    private function authorizeStudent(Request $request, Student $student): void
    {
        abort_unless($student->family_id === $request->user()->id, 403);
    }
}
