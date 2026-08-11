<?php

namespace App\Enums;

enum AdminUserType: string
{
    case Professor = 'professor';
    case Assistant = 'assistant';
    case Director = 'director';
}
