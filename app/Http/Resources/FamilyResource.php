<?php

namespace App\Http\Resources;

use App\Models\Family;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Family
 */
class FamilyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lastNameOne' => $this->last_name_one,
            'lastNameTwo' => $this->last_name_two,
            'user' => $this->user,
            'aboutUs' => $this->about_us,
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
