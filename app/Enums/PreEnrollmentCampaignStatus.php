<?php

namespace App\Enums;

enum PreEnrollmentCampaignStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Closed = 'closed';
    case Archived = 'archived';
}
