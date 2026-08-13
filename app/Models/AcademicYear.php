<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kinder_id', 'year', 'start_date', 'end_date'])]
class AcademicYear extends Model
{
    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
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
     * The academic year whose start/end date range contains today, for the given kinder.
     *
     * The school year runs Feb-Dec (not the calendar year), so "current" can't be derived
     * from just the system date/year and must come from start_date/end_date.
     */
    public static function current(int $kinderId): ?self
    {
        return static::query()
            ->where('kinder_id', $kinderId)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->orderByDesc('start_date')
            ->first();
    }

    /**
     * @return HasMany<Group, $this>
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
