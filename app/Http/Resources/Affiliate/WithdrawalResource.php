<?php

namespace App\Http\Resources\Affiliate;

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
            'amount' => $this->amount,
            'status' => $this->status,
            'status_color' => $this->status_color,
            'method' => $this->method,
            'iban_rib' => $this->iban_rib,
            'bank_type' => $this->bank_type,
            'notes' => $this->notes,
            'payment_ref' => $this->payment_ref,
            'evidence_path' => $this->evidence_path,
            'evidence_url' => $this->evidence_path ? url('storage/' . $this->evidence_path) : null,
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'paid_at' => $this->paid_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Computed attributes
            'total_commission_amount' => $this->total_commission_amount,
            'commission_count' => $this->commission_count,
            
            // Status badge info for UI
            'status_badge' => $this->getStatusBadge(),
            
            // Relationships
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'withdrawal_id' => $item->withdrawal_id,
                        'commission_id' => $item->commission_id,
                        'amount' => $item->amount,
                        'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
                        'commission' => $this->when($item->relationLoaded('commission'), function () use ($item) {
                            return [
                                'id' => $item->commission->id,
                                'amount' => $item->commission->amount,
                                'status' => $item->commission->status,
                                'type' => $item->commission->type,
                                'created_at' => $item->commission->created_at?->format('Y-m-d H:i:s'),
                                'commande' => $this->when($item->commission->relationLoaded('commande'), function () use ($item) {
                                    return [
                                        'id' => $item->commission->commande->id,
                                        'statut' => $item->commission->commande->statut,
                                        'total_ttc' => $item->commission->commande->total_ttc,
                                        'created_at' => $item->commission->commande->created_at?->format('Y-m-d H:i:s'),
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
        ];
    }

    /**
     * Get status badge information for UI display.
     */
    private function getStatusBadge(): array
    {
        $statusMap = [
            'pending' => ['color' => 'warning', 'text' => 'En attente'],
            'approved' => ['color' => 'info', 'text' => 'Approuvé'],
            'in_payment' => ['color' => 'primary', 'text' => 'En cours de paiement'],
            'paid' => ['color' => 'success', 'text' => 'Payé'],
            'rejected' => ['color' => 'error', 'text' => 'Rejeté'],
            'canceled' => ['color' => 'secondary', 'text' => 'Annulé'],
        ];

        return $statusMap[$this->status] ?? ['color' => 'default', 'text' => $this->status];
    }
}
