<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['campaign_id', 'family_id', 'draft_payload', 'revision', 'submitted_at'])]
class PreEnrollmentFamilyDraft extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'draft_payload' => 'array',
            'revision' => 'integer',
            'submitted_at' => 'datetime',
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
     * @return BelongsTo<Family, $this>
     */
    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    /**
     * @return HasMany<PreEnrollmentFieldChange, $this>
     */
    public function fieldChanges(): HasMany
    {
        return $this->hasMany(PreEnrollmentFieldChange::class, 'family_draft_id');
    }

    /**
     * Every column `guardians` requires NOT NULL — a role's block must be all-or-nothing before
     * FormApprovalService can safely upsert it as a real Guardian row.
     *
     * @var array<int, string>
     */
    public const GUARDIAN_REQUIRED_FIELDS = [
        'name', 'lastNameOne', 'nationality', 'idNumber', 'maritalStatus',
        'educationLevel', 'occupation', 'workplace', 'mobilePhone', 'address', 'email',
    ];

    public function hasCompleteGuardian(string $role): bool
    {
        foreach (self::GUARDIAN_REQUIRED_FIELDS as $field) {
            $value = $this->draft_payload["guardians.{$role}.{$field}"]['value'] ?? null;

            if ($value === null || $value === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * At least one parent must be fully identified before the family can submit — a household
     * with zero complete guardian blocks has nothing for FormApprovalService to enroll against.
     */
    public function hasAnyCompleteGuardian(): bool
    {
        return $this->hasCompleteGuardian('mother') || $this->hasCompleteGuardian('father');
    }
}
