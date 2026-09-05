<?php

namespace App\Services\PreEnrollment;

use App\Enums\ActorType;
use App\Models\PreEnrollmentFamilyDraft;
use App\Models\PreEnrollmentFieldChange;
use App\Models\PreEnrollmentForm;

/**
 * Writes `pre_enrollment_field_changes` rows. Per §9 Q11, this is called only at family submit
 * (diffing the submitted payload against each field's original sourceValue) and on every
 * director correction — never on every autosave keystroke.
 */
class FieldChangeRecorder
{
    public function recordFormChanges(PreEnrollmentForm $form, array $oldPayload, array $newPayload, ActorType $actorType, int $actorId): void
    {
        foreach ($this->changedPaths($oldPayload, $newPayload) as $path => [$old, $new]) {
            PreEnrollmentFieldChange::create([
                'form_id' => $form->id,
                'field_path' => $path,
                'old_value' => $this->stringify($old),
                'new_value' => $this->stringify($new),
                'actor_type' => $actorType,
                'actor_id' => $actorId,
            ]);
        }
    }

    public function recordFamilyDraftChanges(PreEnrollmentFamilyDraft $draft, array $oldPayload, array $newPayload, ActorType $actorType, int $actorId): void
    {
        foreach ($this->changedPaths($oldPayload, $newPayload) as $path => [$old, $new]) {
            PreEnrollmentFieldChange::create([
                'family_draft_id' => $draft->id,
                'field_path' => $path,
                'old_value' => $this->stringify($old),
                'new_value' => $this->stringify($new),
                'actor_type' => $actorType,
                'actor_id' => $actorId,
            ]);
        }
    }

    /**
     * "Diff at submit" per §9 Q11: each field's own `sourceValue` (the pre-fill baseline, set
     * once at campaign-open) stands in for the "old" payload, so only what the family actually
     * changed from what we already knew gets logged — not every field they merely confirmed.
     *
     * @param  array<string, array{value: mixed, sourceValue: mixed}>  $payload
     */
    public function recordFormSubmission(PreEnrollmentForm $form, array $payload, ActorType $actorType, int $actorId): void
    {
        $baseline = array_map(fn ($entry) => ['value' => $entry['sourceValue'] ?? null], $payload);

        $this->recordFormChanges($form, $baseline, $payload, $actorType, $actorId);
    }

    /**
     * @param  array<string, array{value: mixed, sourceValue: mixed}>  $payload
     */
    public function recordFamilyDraftSubmission(PreEnrollmentFamilyDraft $draft, array $payload, ActorType $actorType, int $actorId): void
    {
        $baseline = array_map(fn ($entry) => ['value' => $entry['sourceValue'] ?? null], $payload);

        $this->recordFamilyDraftChanges($draft, $baseline, $payload, $actorType, $actorId);
    }

    /**
     * @param  array<string, array{value: mixed, sourceValue: mixed}>  $old
     * @param  array<string, array{value: mixed, sourceValue: mixed}>  $new
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    private function changedPaths(array $old, array $new): array
    {
        $changed = [];

        foreach ($new as $path => $entry) {
            $newValue = $this->extractValue($entry);
            $oldValue = $this->extractValue($old[$path] ?? null);

            if ($oldValue !== $newValue) {
                $changed[$path] = [$oldValue, $newValue];
            }
        }

        return $changed;
    }

    private function extractValue(mixed $entry): mixed
    {
        return is_array($entry) ? ($entry['value'] ?? null) : $entry;
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_scalar($value) ? (string) $value : json_encode($value);
    }
}
