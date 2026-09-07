<?php

namespace App\Models;

use App\Enums\AdminUserType;
use App\Enums\CredentialMethod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'kinder_id', 'name', 'email', 'phone', 'type', 'password',
    'emergency_phone', 'emergency_name', 'birth_date', 'hire_date',
    'status', 'address', 'psych_exam_date',
    'credential_method', 'must_reset_password', 'invite_token', 'invite_expires_at',
])]
#[Hidden(['password', 'invite_token'])]
class AdminUser extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'type' => AdminUserType::class,
            'status' => 'boolean',
            'birth_date' => 'date',
            'hire_date' => 'date',
            'psych_exam_date' => 'date',
            'credential_method' => CredentialMethod::class,
            'must_reset_password' => 'boolean',
            'invite_expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Kinder, $this>
     */
    public function kinder(): BelongsTo
    {
        return $this->belongsTo(Kinder::class);
    }

    /**
     * @return HasMany<Group, $this>
     */
    public function professorGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'professor_id');
    }

    /**
     * @return HasMany<Group, $this>
     */
    public function assistantGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'assistant_id');
    }
}
