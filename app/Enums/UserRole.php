<?php

namespace App\Enums;

enum UserRole: string
{
    case Public = 'public';
    case McmcStaff = 'mcmc_staff';
    case AgencyStaff = 'agency_staff';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public User',
            self::McmcStaff => 'MCMC Staff',
            self::AgencyStaff => 'Agency Staff',
        };
    }
}
