<?php

namespace App\Models;

use App\Enums\GuardianRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'family_id', 'role', 'name', 'last_name', 'nationality', 'national_id', 'age',
    'civil_status', 'religion', 'education', 'profession', 'workplace', 'cell_phone',
    'work_phone', 'lives_with_child', 'address', 'email', 'uses_whatsapp',
    'uses_facebook', 'uses_instagram', 'uses_threads',
])]
class Guardian extends Model
{
    protected function casts(): array
    {
        return [
            'role' => GuardianRole::class,
            'lives_with_child' => 'boolean',
            'uses_whatsapp' => 'boolean',
            'uses_facebook' => 'boolean',
            'uses_instagram' => 'boolean',
            'uses_threads' => 'boolean',
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
