<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'main_color', 'second_color', 'font_name'])]
class Kinder extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'created_date' => 'datetime',
        ];
    }

    /**
     * @return HasMany<AcademicYear, $this>
     */
    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    /**
     * @return HasMany<AdminUser, $this>
     */
    public function adminUsers(): HasMany
    {
        return $this->hasMany(AdminUser::class);
    }

    /**
     * @return HasMany<Family, $this>
     */
    public function families(): HasMany
    {
        return $this->hasMany(Family::class);
    }
}
