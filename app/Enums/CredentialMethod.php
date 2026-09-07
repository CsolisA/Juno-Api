<?php

namespace App\Enums;

enum CredentialMethod: string
{
    case Invite = 'invite';
    case TempPassword = 'temp_password';
}
