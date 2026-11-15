<?php

namespace App\Enums;

enum ClarificationTopic: string
{
    case JurisdictionScope = 'Jurisdiction / scope';
    case MissingEvidence = 'Missing or unclear evidence';
    case SourceVerification = 'Source verification';
    case SubmitterFollowUp = 'Submitter follow-up';
    case RelatedDuplicate = 'Related or duplicate cases';
    case Other = 'Other';

    /**
     * Display name in the current language. The stored value stays English.
     */
    public function label(?string $locale = null): string
    {
        return __($this->value, [], $locale);
    }
}
