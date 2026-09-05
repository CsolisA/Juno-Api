<?php

namespace App\Services\PreEnrollment;

use App\Models\Grade;

final readonly class ProgressionResult
{
    public function __construct(
        public ?Grade $targetGrade,
        public bool $isGraduating,
    ) {}
}
