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

    /**
     * Display wording matching the requirement spec ("Verified as True",
     * "Identified as Fake"). The stored/backing value is left as-is to
     * avoid a data migration for existing rows.
     */
    public function label(): string
    {
        return match ($this) {
            self::VerifiedTrue => 'Verified as True',
            self::IdentifiedFake => 'Identified as Fake',
            default => $this->value,
        };
    }
}
