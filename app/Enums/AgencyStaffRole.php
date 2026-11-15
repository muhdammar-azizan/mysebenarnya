<?php

namespace App\Enums;

/**
 * Sub-role for users with role=agency_staff only. Admins can edit the
 * agency's own profile and invite/manage other agency staff; reviewers can
 * only work on assigned inquiries.
 */
enum AgencyStaffRole: string
{
    case Admin = 'admin';
    case Reviewer = 'reviewer';

    public function label(?string $locale = null): string
    {
        return __(match ($this) {
            self::Admin => 'Admin',
            self::Reviewer => 'Reviewer',
        }, [], $locale);
    }
}
