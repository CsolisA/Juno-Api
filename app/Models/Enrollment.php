<?php

namespace App\Models;

use App\Enums\Schedule;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'student_id', 'academic_year_id', 'group_id', 'schedule', 'enrollment_fee_amount',
    'monthly_fee_amount', 'uniform_size', 'uniform_qty_shirt', 'uniform_qty_short',
    'doc_birth_cert', 'doc_vaccine_card', 'doc_mother_id', 'doc_father_id',
    'doc_authorized_ids', 'doc_photos', 'doc_service_contract',
])]
class Enrollment extends Model
{
    protected function casts(): array
    {
        return [
            'schedule' => Schedule::class,
            'enrollment_fee_amount' => 'decimal:2',
            'monthly_fee_amount' => 'decimal:2',
            'doc_birth_cert' => 'boolean',
            'doc_vaccine_card' => 'boolean',
            'doc_mother_id' => 'boolean',
            'doc_father_id' => 'boolean',
            'doc_authorized_ids' => 'boolean',
            'doc_photos' => 'boolean',
            'doc_service_contract' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<AcademicYear, $this>
     */
    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
