<?php

namespace App\Http\Resources\Affiliate;

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
            'base_amount' => $this->base_amount ?? 0,
            'rate' => $this->rate ?? null,
            'qty' => $this->qty ?? 1,
            'amount' => $this->amount ?? 0,
            'currency' => $this->currency ?? 'MAD',
            'status' => $this->status,
            'rule_code' => $this->rule_code,
            'notes' => $this->notes,
            'eligible_at' => $this->eligible_at?->format('Y-m-d H:i:s'),
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'paid_at' => $this->paid_at?->format('Y-m-d H:i:s'),
            'paid_withdrawal_id' => $this->paid_withdrawal_id,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Relationships
            'commande' => $this->whenLoaded('commande', function () {
                return [
                    'id' => $this->commande->id,
                    'statut' => $this->commande->statut,
                    'total_ttc' => $this->commande->total_ttc ?? 0,
                    'devise' => $this->commande->devise ?? 'MAD',
                    'created_at' => $this->commande->created_at?->format('Y-m-d H:i:s'),
                ];
            }),
            
            'commandeArticle' => $this->whenLoaded('commandeArticle', function () {
                return [
                    'id' => $this->commandeArticle->id,
                    'quantite' => $this->commandeArticle->quantite ?? 1,
                    'prix_unitaire' => $this->commandeArticle->prix_unitaire ?? 0,
                    'total_ligne' => $this->commandeArticle->total_ligne ?? 0,
                    'produit' => $this->when(
                        $this->commandeArticle->relationLoaded('produit'),
                        function () {
                            return [
                                'id' => $this->commandeArticle->produit->id,
                                'titre' => $this->commandeArticle->produit->titre ?? 'N/A',
                                'sku' => $this->commandeArticle->produit->sku ?? null,
                                'prix_vente' => $this->commandeArticle->produit->prix_vente ?? 0,
                            ];
                        }
                    ),
                ];
            }),
            
            // Status badge info for UI
            'status_badge' => $this->getStatusBadge(),
            
            // Computed display values
            'order_reference' => $this->commande ? "#{$this->commande->id}" : '#N/A',
            'product_title' => $this->commandeArticle?->produit?->titre ?? 'N/A',
            'formatted_base_amount' => $this->formatCurrency($this->base_amount ?? 0),
            'formatted_rate' => $this->rate ? number_format($this->rate * 100, 2) . '%' : 'N/A',
            'formatted_amount' => $this->formatCurrency($this->amount ?? 0),
        ];
    }
    
    /**
     * Format currency value for display
     */
    private function formatCurrency($amount): string
    {
        if (is_null($amount) || !is_numeric($amount)) {
            return '0,00 MAD';
        }
        
        return number_format((float) $amount, 2, ',', ' ') . ' MAD';
    }
    
    /**
     * Get status badge configuration for UI
     */
    private function getStatusBadge(): array
    {
        $badges = [
            'pending_calc' => ['color' => 'secondary', 'text' => 'En calcul'],
            'calculated' => ['color' => 'info', 'text' => 'Calculée'],
            'eligible' => ['color' => 'warning', 'text' => 'En attente'],
            'approved' => ['color' => 'success', 'text' => 'Approuvée'],
            'rejected' => ['color' => 'error', 'text' => 'Rejetée'],
            'paid' => ['color' => 'primary', 'text' => 'Payée'],
            'adjusted' => ['color' => 'warning', 'text' => 'Ajustée'],
            'canceled' => ['color' => 'secondary', 'text' => 'Annulée'],
        ];
        
        return $badges[$this->status] ?? ['color' => 'secondary', 'text' => ucfirst($this->status)];
    }
}
