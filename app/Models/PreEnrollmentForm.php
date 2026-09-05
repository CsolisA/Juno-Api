<?php

namespace App\Models;

use App\Enums\ExclusionReason;
use App\Enums\PreEnrollmentFormStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'campaign_id', 'student_id', 'family_id', 'current_grade_id', 'current_group_id',
    'target_grade_id', 'target_grade_overridden', 'target_grade_overridden_by',
    'target_grade_overridden_at', 'status', 'is_excluded', 'exclusion_reason',
    'exclusion_reason_detail', 'excluded_by', 'excluded_at', 'draft_payload',
    'submitted_snapshot', 'revision', 'completion_percent', 'family_notes',
    'has_reported_issue', 'started_at', 'submitted_at', 'reviewed_at', 'applied_at',
    'reviewed_by', 'projected_enrollment_id',
])]
class PreEnrollmentForm extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'target_grade_overridden' => 'boolean',
            'target_grade_overridden_at' => 'datetime',
            'status' => PreEnrollmentFormStatus::class,
            'is_excluded' => 'boolean',
            'exclusion_reason' => ExclusionReason::class,
            'excluded_at' => 'datetime',
            'draft_payload' => 'array',
            'submitted_snapshot' => 'array',
            'revision' => 'integer',
            'completion_percent' => 'integer',
            'has_reported_issue' => 'boolean',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PreEnrollmentCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PreEnrollmentCampaign::class, 'campaign_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * @return BelongsTo<Grade, $this>
     */
    public function currentGrade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'current_grade_id');
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function currentGroup(): BelongsTo
    {
        return $this->belongsTo(Group::class, 'current_group_id');
    }

    /**
     * @return BelongsTo<Grade, $this>
     */
    public function targetGrade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'target_grade_id');
    }

    /**
     * @return BelongsTo<AdminUser, $this>
     */
    public function targetGradeOverriddenBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'target_grade_overridden_by');
    }

    /**
     * @return BelongsTo<AdminUser, $this>
     */
    public function excludedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'excluded_by');
    }

    /**
     * @return BelongsTo<AdminUser, $this>
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'reviewed_by');
    }

    /**
     * @return BelongsTo<Enrollment, $this>
     */
    public function projectedEnrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class, 'projected_enrollment_id');
    }

    /**
     * @return HasMany<PreEnrollmentFieldChange, $this>
     */
    public function fieldChanges(): HasMany
    {
        return $this->hasMany(PreEnrollmentFieldChange::class, 'form_id');
    }

    /**
     * v1's required-field list for the student section — a defensible subset of the full paper
     * boleta (§2.1: fields with no source data are simply blank until the family fills them).
     *
     * @var array<int, string>
     */
    public const REQUIRED_DRAFT_PATHS = [
        'student.scheduleId',
        'student.transportType',
        'student.bloodType',
        'student.province',
        'student.canton',
        'student.address',
        'student.phone',
        'student.insurancePolicyNumber',
    ];

    /**
     * Pure function over draft_payload — no side effects, no DB writes.
     */
    public function calculateCompletionPercent(): int
    {
        $filled = 0;

        foreach (self::REQUIRED_DRAFT_PATHS as $path) {
            $value = $this->draft_payload[$path]['value'] ?? null;

            if ($value !== null && $value !== '') {
                $filled++;
            }
        }

        return (int) round(($filled / count(self::REQUIRED_DRAFT_PATHS)) * 100);
    }
}
