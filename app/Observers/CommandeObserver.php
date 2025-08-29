<?php

namespace App\Observers;

use App\Models\Commande;
use App\Services\OrderStatusHistoryService;
use Illuminate\Support\Facades\Auth;

class CommandeObserver
{
    protected OrderStatusHistoryService $historyService;

    public function __construct(OrderStatusHistoryService $historyService)
    {
        $this->historyService = $historyService;
    }

    /**
     * Handle the Commande "created" event.
     */
    public function created(Commande $commande): void
    {
        // Log initial status when order is created
        $source = 'system';
        
        // Determine source based on current user context
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->hasRole('admin')) {
                $source = 'admin';
            } elseif ($user->hasRole('affiliate')) {
                $source = 'affiliate';
            }
        }

        $this->historyService->logInitialStatus($commande, $source);
    }

    /**
     * Handle the Commande "updated" event.
     */
    public function updated(Commande $commande): void
    {
        // Check if status has changed
        if ($commande->isDirty('statut')) {
            $oldStatus = $commande->getOriginal('statut');
            $newStatus = $commande->statut;

            // Determine source based on current user context
            $source = 'system';
            $note = null;

            if (Auth::check()) {
                $user = Auth::user();
                if ($user->hasRole('admin')) {
                    $source = 'admin';
                } elseif ($user->hasRole('affiliate')) {
                    $source = 'affiliate';
                }
            }

            // Check if notes were also updated to include in history
            if ($commande->isDirty('notes')) {
                $oldNotes = $commande->getOriginal('notes');
                $newNotes = $commande->notes;
                
                // Extract the new note (assuming it's appended)
                if ($newNotes && $oldNotes) {
                    $note = str_replace($oldNotes, '', $newNotes);
                    $note = trim($note, "\n ");
                } elseif ($newNotes && !$oldNotes) {
                    $note = $newNotes;
                }
            }

            // Log the status change
            $this->historyService->logStatusChange(
                $commande,
                $oldStatus,
                $newStatus,
                $source,
                $note
            );
        }
    }

    /**
     * Handle the Commande "deleted" event.
     */
    public function deleted(Commande $commande): void
    {
        // Log deletion if using soft deletes
        if (method_exists($commande, 'trashed') && $commande->trashed()) {
            $this->historyService->logStatusChange(
                $commande,
                $commande->statut,
                'deleted',
                Auth::check() && Auth::user()->hasRole('admin') ? 'admin' : 'system',
                'Commande supprimée'
            );
        }
    }

    /**
     * Handle the Commande "restored" event.
     */
    public function restored(Commande $commande): void
    {
        // Log restoration if using soft deletes
        $this->historyService->logStatusChange(
            $commande,
            'deleted',
            $commande->statut,
            Auth::check() && Auth::user()->hasRole('admin') ? 'admin' : 'system',
            'Commande restaurée'
        );
    }
}
