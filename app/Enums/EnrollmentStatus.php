<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case Projected = 'projected';
    case Active = 'active';
    case Withdrawn = 'withdrawn';
    case Graduated = 'graduated';
}
