<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['grade_id', 'academic_year_id', 'professor_id', 'assistant_id'])]
class Group extends Model
{
    public $timestamps = false;

    protected $table = 'groups';

    /**
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<AdminUser, $this>
     */
    public function professor(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'professor_id');
    }

    /**
     * @return BelongsTo<AdminUser, $this>
     */
    public function assistant(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'assistant_id');
    }

    /**
     * @return BelongsToMany<Student, $this>
     */
    public function students(): BelongsToMany
    {
        return $this->belongsToMany(Student::class, 'student_group');
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
