<?php

namespace App\Models;

use App\Enums\EnrollmentSource;
use App\Enums\EnrollmentStatus;
use App\Enums\TransportType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'student_id', 'academic_year_id', 'group_id', 'grade_id', 'schedule_id', 'status',
    'source', 'transport_type', 'medical_conditions', 'diagnosis', 'takes_medication',
    'medication_details', 'practices_sport', 'sport_details', 'extra_classes',
    'extra_classes_detail', 'enrollment_fee_amount', 'monthly_fee_amount', 'uniform_size',
    'uniform_qty_shirt', 'uniform_qty_short', 'doc_birth_cert', 'doc_vaccine_card',
    'doc_mother_id', 'doc_father_id', 'doc_authorized_ids', 'doc_photos',
    'doc_service_contract', 'pre_enrollment_form_id',
])]
class Enrollment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
            'source' => EnrollmentSource::class,
            'transport_type' => TransportType::class,
            'takes_medication' => 'boolean',
            'practices_sport' => 'boolean',
            'extra_classes' => 'boolean',
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

    /**
     * @return BelongsTo<Grade, $this>
     */
    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /**
     * @return BelongsTo<Schedule, $this>
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * @return BelongsTo<PreEnrollmentForm, $this>
     */
    public function preEnrollmentForm(): BelongsTo
    {
        return $this->belongsTo(PreEnrollmentForm::class);
    }

    /**
     * @return HasMany<TransportContact, $this>
     */
    public function transportContacts(): HasMany
    {
        return $this->hasMany(TransportContact::class);
    }
}
