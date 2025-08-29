<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WithdrawalResource extends JsonResource
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
            'user_id' => $this->user_id,
            'amount' => $this->amount,
            'status' => $this->status,
            'status_color' => $this->status_color,
            'method' => $this->method,
            'iban_rib' => $this->iban_rib,
            'bank_type' => $this->bank_type,
            'notes' => $this->notes,
            'admin_reason' => $this->admin_reason,
            'payment_ref' => $this->payment_ref,
            'evidence_path' => $this->evidence_path,
            'evidence_url' => $this->evidence_path ? url('storage/' . $this->evidence_path) : null,
            'approved_at' => $this->approved_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'meta' => $this->meta,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Computed attributes
            'total_commission_amount' => $this->total_commission_amount,
            'commission_count' => $this->commission_count,
            
            // Relationships
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'nom_complet' => $this->user->nom_complet,
                    'email' => $this->user->email,
                    'telephone' => $this->user->telephone,
                    'rib' => $this->user->rib,
                    'bank_type' => $this->user->bank_type,
                ];
            }),
            
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'withdrawal_id' => $item->withdrawal_id,
                        'commission_id' => $item->commission_id,
                        'amount' => $item->amount,
                        'created_at' => $item->created_at?->toISOString(),
                        'commission' => $this->when($item->relationLoaded('commission'), function () use ($item) {
                            return [
                                'id' => $item->commission->id,
                                'amount' => $item->commission->amount,
                                'status' => $item->commission->status,
                                'type' => $item->commission->type,
                                'created_at' => $item->commission->created_at?->toISOString(),
                                'commande' => $this->when($item->commission->relationLoaded('commande'), function () use ($item) {
                                    return [
                                        'id' => $item->commission->commande->id,
                                        'statut' => $item->commission->commande->statut,
                                        'total_ttc' => $item->commission->commande->total_ttc,
                                        'created_at' => $item->commission->commande->created_at?->toISOString(),
                                    ];
                                }),
                                'commande_article' => $this->when($item->commission->relationLoaded('commandeArticle'), function () use ($item) {
                                    return [
                                        'id' => $item->commission->commandeArticle->id,
                                        'quantite' => $item->commission->commandeArticle->quantite,
                                        'prix_unitaire' => $item->commission->commandeArticle->prix_unitaire,
                                        'total_ligne' => $item->commission->commandeArticle->total_ligne,
                                        'type_command' => $item->commission->commandeArticle->type_command,
                                        'produit' => $this->when($item->commission->commandeArticle->relationLoaded('produit'), function () use ($item) {
                                            return [
                                                'id' => $item->commission->commandeArticle->produit->id,
                                                'titre' => $item->commission->commandeArticle->produit->titre,
                                                'sku' => $item->commission->commandeArticle->produit->sku,
                                                'prix_vente' => $item->commission->commandeArticle->produit->prix_vente,
                                            ];
                                        }),
                                    ];
                                }),
                                'produit' => $this->when(
                                    $item->commission->relationLoaded('commandeArticle') && 
                                    $item->commission->commandeArticle?->relationLoaded('produit'), 
                                    function () use ($item) {
                                        return [
                                            'id' => $item->commission->commandeArticle->produit->id,
                                            'titre' => $item->commission->commandeArticle->produit->titre,
                                            'sku' => $item->commission->commandeArticle->produit->sku,
                                        ];
                                    }
                                ),
                            ];
                        }),
                    ];
                });
            }),
            
            // Action permissions
            'can_approve' => $this->canBeApproved(),
            'can_reject' => $this->canBeRejected(),
            'can_mark_in_payment' => $this->canBeMarkedInPayment(),
            'can_mark_paid' => $this->canBeMarkedPaid(),
            'can_cancel' => $this->canBeCanceled(),
        ];
    }
}
