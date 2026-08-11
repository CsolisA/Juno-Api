<?php

namespace App\Enums;

enum Schedule: string
{
    case InPerson = 'inPerson';
    case Alternating = 'alternating';
    case Daycare = 'daycare';
}
