<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /**
     * Determine if the user can view any tickets.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can view the ticket.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can create tickets.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can update the ticket.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can delete the ticket.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can restore the ticket.
     */
    public function restore(User $user, Ticket $ticket): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can permanently delete the ticket.
     */
    public function forceDelete(User $user, Ticket $ticket): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can assign tickets.
     */
    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can change ticket status.
     */
    public function changeStatus(User $user, Ticket $ticket): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can add messages to the ticket.
     */
    public function addMessage(User $user, Ticket $ticket): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can add internal messages to the ticket.
     */
    public function addInternalMessage(User $user, Ticket $ticket): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine if the user can manage ticket relations.
     */
    public function manageRelations(User $user, Ticket $ticket): bool
    {
        return $user->hasRole('admin');
    }
}
