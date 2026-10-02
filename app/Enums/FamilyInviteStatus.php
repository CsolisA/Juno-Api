<?php

namespace App\Enums;

enum FamilyInviteStatus: string
{
    case Pending = 'pending';
    case Submitted = 'submitted';
    case Revoked = 'revoked';
}
