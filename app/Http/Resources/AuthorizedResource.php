<?php

namespace App\Http\Resources;

use App\Models\Authorized;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Authorized
 */
class AuthorizedResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'lastName' => $this->last_name,
            'lastNameTwo' => $this->last_name_two,
            'phone' => $this->phone,
            'related' => $this->relationship,
            'pickUp' => (bool) $this->pick_up,
            'livesWithChild' => (bool) $this->lives_with_child,
            'ocupation' => $this->occupation,
            'communication' => (bool) $this->communication,
            'status' => $this->status ? 'active' : 'inactive',
        ];
    }
}
