<?php

namespace App\Enums;

enum GuardianRole: string
{
    case Mother = 'mother';
    case Father = 'father';
    case Other = 'other';
}
