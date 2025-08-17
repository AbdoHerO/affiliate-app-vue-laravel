<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'commande_id' => $this->commande_id,
            'commande_article_id' => $this->commande_article_id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'base_amount' => $this->base_amount,
            'rate' => $this->rate,
            'qty' => $this->qty,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status,
            'rule_code' => $this->rule_code,
            'notes' => $this->notes,
            'eligible_at' => $this->eligible_at?->format('Y-m-d H:i:s'),
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'paid_at' => $this->paid_at?->format('Y-m-d H:i:s'),
            'paid_withdrawal_id' => $this->paid_withdrawal_id,
            'meta' => $this->meta,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            
            // Relationships
            'affiliate' => $this->whenLoaded('affiliate', function () {
                return [
                    'id' => $this->affiliate->id,
                    'nom_complet' => $this->affiliate->nom_complet,
                    'email' => $this->affiliate->email,
                    'telephone' => $this->affiliate->telephone ?? null,
                ];
            }),
            
            'commande' => $this->whenLoaded('commande', function () {
                return [
                    'id' => $this->commande->id,
                    'statut' => $this->commande->statut,
                    'total_ttc' => $this->commande->total_ttc,
                    'devise' => $this->commande->devise ?? 'MAD',
                    'notes' => $this->commande->notes,
                    'created_at' => $this->commande->created_at->format('Y-m-d H:i:s'),
                ];
            }),
            
            'commande_article' => $this->commandeArticle ? [
                'id' => $this->commandeArticle->id,
                'quantite' => $this->commandeArticle->quantite,
                'prix_unitaire' => $this->commandeArticle->prix_unitaire,
                'total_ligne' => $this->commandeArticle->total_ligne,
                'produit' => $this->commandeArticle->produit ? [
                    'id' => $this->commandeArticle->produit->id,
                    'titre' => $this->commandeArticle->produit->titre,
                    'prix_vente' => $this->commandeArticle->produit->prix_vente,
                ] : null,
            ] : null,
            
            // Computed properties
            'can_be_approved' => $this->canBeApproved(),
            'can_be_rejected' => $this->canBeRejected(),
            'can_be_adjusted' => $this->canBeAdjusted(),
            
            // Status badge info
            'status_badge' => $this->getStatusBadge(),
            
            // Legacy fields for backward compatibility
            'montant' => $this->amount, // Map new amount to legacy montant
            'statut' => $this->mapStatusToLegacy($this->status),
        ];
    }
    
    /**
     * Get status badge configuration for UI
     */
    protected function getStatusBadge(): array
    {
        $badges = [
            'pending_calc' => ['color' => 'secondary', 'text' => 'En calcul'],
            'calculated' => ['color' => 'info', 'text' => 'Calculée'],
            'eligible' => ['color' => 'primary', 'text' => 'Éligible'],
            'approved' => ['color' => 'success', 'text' => 'Approuvée'],
            'rejected' => ['color' => 'error', 'text' => 'Rejetée'],
            'paid' => ['color' => 'success', 'text' => 'Payée'],
            'adjusted' => ['color' => 'warning', 'text' => 'Ajustée'],
            'canceled' => ['color' => 'secondary', 'text' => 'Annulée'],
        ];
        
        return $badges[$this->status] ?? ['color' => 'secondary', 'text' => $this->status];
    }
    
    /**
     * Map new status to legacy status for backward compatibility
     */
    protected function mapStatusToLegacy(string $status): string
    {
        $mapping = [
            'pending_calc' => 'en_attente',
            'calculated' => 'en_attente',
            'eligible' => 'valide',
            'approved' => 'valide',
            'rejected' => 'annule',
            'paid' => 'paye',
            'adjusted' => 'valide',
            'canceled' => 'annule',
        ];
        
        return $mapping[$status] ?? 'en_attente';
    }
}
