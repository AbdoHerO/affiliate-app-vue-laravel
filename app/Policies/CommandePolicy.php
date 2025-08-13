<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Commande;

class CommandePolicy
{
    /**
     * Determine if the user can view any orders.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(['admin', 'affiliate']);
    }

    /**
     * Determine if the user can view the order.
     */
    public function view(User $user, Commande $commande): bool
    {
        // Admins can view all orders
        if ($user->hasRole('admin')) {
            return true;
        }

        // Affiliates can only view their own orders
        if ($user->hasRole('affiliate') && $user->isApprovedAffiliate()) {
            return $commande->user_id === $user->id;
        }

        return false;
    }

    /**
     * Determine if the user can create orders.
     */
    public function create(User $user): bool
    {
        // Only approved affiliates can create orders
        return $user->isApprovedAffiliate();
    }

    /**
     * Determine if the user can update the order.
     */
    public function update(User $user, Commande $commande): bool
    {
        // Admins can update all orders
        if ($user->hasRole('admin')) {
            return true;
        }

        // Affiliates can only update their own orders if they're in certain statuses
        if ($user->hasRole('affiliate') && $user->isApprovedAffiliate()) {
            return $commande->user_id === $user->id &&
                   in_array($commande->statut, ['en_attente', 'confirmee']);
        }

        return false;
    }

    /**
     * Determine if the user can delete the order.
     */
    public function delete(User $user, Commande $commande): bool
    {
        // Only admins can delete orders
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can manage shipping for the order.
     */
    public function manageShipping(User $user, Commande $commande): bool
    {
        // Only admins can manage shipping
        return $user->hasRole('admin');
    }
}
