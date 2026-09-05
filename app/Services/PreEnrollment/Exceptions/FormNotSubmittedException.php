<?php

namespace App\Services\PreEnrollment\Exceptions;

use RuntimeException;

class FormNotSubmittedException extends RuntimeException
{
    public static function forForm(int $formId): self
    {
        return new self("Form {$formId} must be submitted before it can be approved.");
    }
}
