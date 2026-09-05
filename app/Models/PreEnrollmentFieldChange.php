<?php

namespace App\Models;

use App\Enums\ActorType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['form_id', 'family_draft_id', 'field_path', 'old_value', 'new_value', 'actor_type', 'actor_id'])]
class PreEnrollmentFieldChange extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'actor_type' => ActorType::class,
        ];
    }

    /**
     * @return BelongsTo<PreEnrollmentForm, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(PreEnrollmentForm::class, 'form_id');
    }

    /**
     * @return BelongsTo<PreEnrollmentFamilyDraft, $this>
     */
    public function familyDraft(): BelongsTo
    {
        return $this->belongsTo(PreEnrollmentFamilyDraft::class, 'family_draft_id');
    }
}
