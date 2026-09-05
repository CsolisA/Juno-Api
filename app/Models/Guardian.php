<?php

namespace App\Models;

use App\Enums\GuardianRole;
use App\Enums\IdType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'family_id', 'role', 'name', 'last_name_one', 'last_name_two', 'nationality',
    'id_number', 'id_type', 'birth_date', 'marital_status', 'religion', 'education_level',
    'occupation', 'workplace', 'mobile_phone', 'work_phone', 'lives_with_child', 'address',
    'email', 'uses_whatsapp', 'uses_facebook', 'uses_instagram', 'uses_threads',
    'is_primary_contact', 'status',
])]
class Guardian extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'role' => GuardianRole::class,
            'id_type' => IdType::class,
            'birth_date' => 'date',
            'lives_with_child' => 'boolean',
            'uses_whatsapp' => 'boolean',
            'uses_facebook' => 'boolean',
            'uses_instagram' => 'boolean',
            'uses_threads' => 'boolean',
            'is_primary_contact' => 'boolean',
            'status' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }
}
