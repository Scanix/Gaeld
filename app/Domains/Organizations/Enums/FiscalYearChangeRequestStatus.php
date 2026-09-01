<?php

namespace App\Domains\Organizations\Enums;

enum FiscalYearChangeRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Applied = 'applied';
}
