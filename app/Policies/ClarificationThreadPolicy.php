<?php

namespace App\Policies;

use App\Models\ClarificationThread;
use App\Models\User;

class ClarificationThreadPolicy
{
    /**
     * View the thread: MCMC, the owning agency's own staff, or a consulted
     * agency's own staff.
     */
    public function view(User $user, ClarificationThread $thread): bool
    {
        if ($user->isMcmcStaff()) {
            return true;
        }

        if ($user->isAgencyStaff() && $user->agency_id === $thread->inquiry->agency_id) {
            return true;
        }

        if ($user->isAgencyStaff() && $thread->consults()->where('consulted_agency_id', $user->agency_id)->exists()) {
            return true;
        }

        return false;
    }

    /**
     * Reply as the owning agency (follow-up) or as MCMC.
     */
    public function reply(User $user, ClarificationThread $thread): bool
    {
        if (! $thread->isActive()) {
            return false;
        }

        return $user->isMcmcStaff()
            || ($user->isAgencyStaff() && $user->agency_id === $thread->inquiry->agency_id);
    }

    /**
     * Close the thread: the owning agency's staff or MCMC.
     */
    public function close(User $user, ClarificationThread $thread): bool
    {
        if (! $thread->isActive()) {
            return false;
        }

        return $user->isMcmcStaff()
            || ($user->isAgencyStaff() && $user->agency_id === $thread->inquiry->agency_id);
    }

    /**
     * Invite another agency to consult: MCMC only.
     */
    public function inviteConsult(User $user, ClarificationThread $thread): bool
    {
        return $user->isMcmcStaff() && $thread->isActive();
    }
}
