<?php

namespace App\Models;

use App\Enums\AcademicYearStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['kinder_id', 'year', 'start_date', 'end_date', 'status', 'campaign_confirmed'])]
class AcademicYear extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => AcademicYearStatus::class,
            'campaign_confirmed' => 'boolean',
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
     * The academic year a director has explicitly activated for this kinder — the single source
     * of truth for "current year" across the app. At most one row can hold this status at a time
     * (enforced by a unique index on active_status_slot), so this is never ambiguous.
     *
     * Deliberately status-driven, not date-driven: the school year runs Feb-Dec, so a year's
     * start/end dates alone can't say whether it's the one currently in force — a new year's
     * dates can start before the director has actually cut over to it.
     */
    public static function current(int $kinderId): ?self
    {
        return static::query()
            ->where('kinder_id', $kinderId)
            ->where('status', AcademicYearStatus::Activo)
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
