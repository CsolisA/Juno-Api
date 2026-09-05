<?php

namespace App\Services\PreEnrollment\Exceptions;

class NoSourceAcademicYearException extends \RuntimeException
{
    public static function forKinder(int $kinderId): self
    {
        return new self("No current academic year found for kinder {$kinderId}; cannot determine which enrollments to carry forward.");
    }
}
