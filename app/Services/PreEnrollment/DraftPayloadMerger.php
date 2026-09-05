<?php

namespace App\Services\PreEnrollment;

class DraftPayloadMerger
{
    /**
     * Merge a flat dot-path => new-value map (§6.5's autosave `changes` shape) into a stored
     * {path: {value, sourceValue}} payload, preserving each field's own sourceValue. Shared by
     * family autosave and director corrections so both edit payloads the same way.
     *
     * @param  array<string, array{value: mixed, sourceValue: mixed}>  $payload
     * @param  array<string, mixed>  $changes
     * @return array<string, array{value: mixed, sourceValue: mixed}>
     */
    public static function merge(array $payload, array $changes): array
    {
        foreach ($changes as $path => $value) {
            $payload[$path] = [
                'value' => $value,
                'sourceValue' => $payload[$path]['sourceValue'] ?? null,
            ];
        }

        return $payload;
    }
}
