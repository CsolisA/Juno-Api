<?php

namespace App\Models;

use App\Enums\PreEnrollmentCampaignStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'academic_year_id', 'name', 'status', 'due_date', 'enforce_due_date',
    'opened_at', 'closed_at', 'created_by', 'closed_by', 'notes',
])]
class PreEnrollmentCampaign extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => PreEnrollmentCampaignStatus::class,
            'due_date' => 'date',
            'enforce_due_date' => 'boolean',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'created_by');
    }

    /**
     * @return BelongsTo<AdminUser, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'closed_by');
    }

    /**
     * @return HasMany<PreEnrollmentForm, $this>
     */
    public function forms(): HasMany
    {
        return $this->hasMany(PreEnrollmentForm::class, 'campaign_id');
    }

    /**
     * @return HasMany<PreEnrollmentFamilyDraft, $this>
     */
    public function familyDrafts(): HasMany
    {
        return $this->hasMany(PreEnrollmentFamilyDraft::class, 'campaign_id');
    }
}
