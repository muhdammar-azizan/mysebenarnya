<?php

namespace App\Enums;

enum InquiryStatus: string
{
    case Submitted = 'Submitted';
    case UnderInvestigation = 'Under Investigation';
    case VerifiedTrue = 'Verified True';
    case IdentifiedFake = 'Identified Fake';
    case Rejected = 'Rejected';
    case Discarded = 'Discarded';
}
