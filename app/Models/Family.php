<?php

namespace App\Models;

use App\Enums\ReferralSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['kinder_id', 'last_name_one', 'last_name_two', 'user', 'password', 'about_us', 'referral_source'])]
#[Hidden(['password'])]
class Family extends Authenticatable
{
    use HasApiTokens;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'referral_source' => ReferralSource::class,
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
     * @return HasMany<Guardian, $this>
     */
    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
    }

    /**
     * @return HasMany<Student, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }
}
