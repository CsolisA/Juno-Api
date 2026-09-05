<?php

namespace App\Services\PreEnrollment\Exceptions;

class DuplicateActiveCampaignException extends \RuntimeException
{
    public static function forAcademicYear(int $academicYearId): self
    {
        return new self("A draft or open pre-enrollment campaign already exists for academic year {$academicYearId}.");
    }
}
