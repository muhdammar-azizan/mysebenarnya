<?php

namespace App\Enums;

/**
 * Canonical category list, unifying the public submission form's categories,
 * MCMC's triage categories, and agency specializations seen across the design
 * handoff prototypes (which used three inconsistent vocabularies). Agencies
 * pick their specialization from these same 7 substantive cases; "Other" is
 * reserved for inquiries that don't map to any agency's jurisdiction.
 */
enum InquiryCategory: string
{
    case HealthMedical = 'Health & Medical Claims';
    case FinancialScams = 'Financial Scams & Banking';
    case ElectoralPolitical = 'Electoral & Political Content';
    case ConsumerRights = 'Consumer Rights & Pricing';
    case CriminalFraud = 'Criminal & Fraud Referrals';
    case DisasterEmergency = 'Disaster & Emergency Aid';
    case TechnologyDigital = 'Technology & Digital Safety';
    case Other = 'Other';

    /**
     * Display name in the current language. The stored value stays English.
     */
    public function label(?string $locale = null): string
    {
        return __($this->value, [], $locale);
    }
}
