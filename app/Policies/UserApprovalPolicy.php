<?php

namespace App\Policies;

use App\Models\User;

class UserApprovalPolicy
{
    /**
     * Determine if the user can view the approval queue.
     */
    public function viewApprovalQueue(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can approve other users.
     */
    public function approve(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can refuse other users.
     */
    public function refuse(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can resend verification emails.
     */
    public function resendVerification(User $user): bool
    {
        return $user->hasRole('admin');
    }
}
