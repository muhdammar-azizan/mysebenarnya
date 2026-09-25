<?php

namespace App\Policies;

use App\Enums\ConsultStatus;
use App\Models\ClarificationConsult;
use App\Models\User;

class ClarificationConsultPolicy
{
    /**
     * View: MCMC, or the consulted agency's own staff.
     */
    public function view(User $user, ClarificationConsult $consult): bool
    {
        return $user->isMcmcStaff()
            || ($user->isAgencyStaff() && $user->agency_id === $consult->consulted_agency_id);
    }

    /**
     * Reply as the consulted agency's own staff, while still active.
     */
    public function reply(User $user, ClarificationConsult $consult): bool
    {
        return $user->isAgencyStaff()
            && $user->agency_id === $consult->consulted_agency_id
            && $consult->status !== ConsultStatus::Ended
            && $consult->thread->isActive();
    }

    /**
     * End the consultation: MCMC only.
     */
    public function end(User $user, ClarificationConsult $consult): bool
    {
        return $user->isMcmcStaff() && $consult->status !== ConsultStatus::Ended;
    }
}
