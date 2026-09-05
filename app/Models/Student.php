<?php

namespace App\Models;

use App\Enums\IdType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'last_name', 'last_name_two', 'family_id', 'id_number', 'id_type',
    'birth_date', 'insurance_policy_number', 'blood_type', 'nationality', 'province',
    'canton', 'address', 'phone',
])]
class Student extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'id_type' => IdType::class,
            'birth_date' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * @return BelongsToMany<Group, $this>
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'student_group');
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return HasMany<StudentTransport, $this>
     */
    public function transports(): HasMany
    {
        return $this->hasMany(StudentTransport::class);
    }

    /**
     * @return BelongsToMany<Authorized, $this>
     */
    public function authorizedPersons(): BelongsToMany
    {
        return $this->belongsToMany(Authorized::class, 'authorized_student');
    }

    /**
     * Age as of February 15th of the current year, derived from birth_date.
     *
     * @return Attribute<int, never>
     */
    protected function ageAsOfFeb15(): Attribute
    {
        return Attribute::make(
            get: fn (): int => $this->birth_date->diffInYears(Carbon::create(now()->year, 2, 15)),
        );
    }

    /**
     * Whether this student has siblings also enrolled (other students sharing the same family).
     */
    public function hasSiblingsEnrolled(): bool
    {
        return Student::where('family_id', $this->family_id)
            ->where('id', '!=', $this->id)
            ->exists();
    }
}
