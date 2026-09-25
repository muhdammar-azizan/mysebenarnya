<?php

namespace App\Policies;

use App\Enums\InquiryStatus;
use App\Models\Inquiry;
use App\Models\User;

class InquiryPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Owner view: the submitter, or MCMC/agency staff already involved.
     */
    public function view(User $user, Inquiry $inquiry): bool
    {
        if ($user->id === $inquiry->submitted_by) {
            return true;
        }

        if ($user->isMcmcStaff()) {
            return true;
        }

        if ($user->isAgencyStaff() && $user->agency_id === $inquiry->agency_id) {
            return true;
        }

        return false;
    }

    /**
     * Public transparency view (Browse): any authenticated user may view a
     * non-discarded inquiry, with the submitter's identity hidden by the view
     * itself, not by this policy.
     */
    public function viewPublicly(User $user, Inquiry $inquiry): bool
    {
        return $inquiry->status !== InquiryStatus::Discarded;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->isPublic();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Inquiry $inquiry): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Inquiry $inquiry): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Inquiry $inquiry): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Inquiry $inquiry): bool
    {
        return false;
    }
}
