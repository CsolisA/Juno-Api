<?php

namespace App\Enums;

enum PreEnrollmentFormStatus: string
{
    case Excluded = 'excluded';
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Applied = 'applied';
    case NotSubmitted = 'not_submitted';
}
