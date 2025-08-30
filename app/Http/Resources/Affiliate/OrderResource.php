<?php

namespace App\Http\Resources\Affiliate;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'boutique_id' => $this->boutique_id,
            'user_id' => $this->user_id,
            'client_id' => $this->client_id,
            'adresse_id' => $this->adresse_id,
            'client_final_id' => $this->client_final_id,
            'adresse_livraison_id' => $this->adresse_livraison_id,
            'offre_id' => $this->offre_id,
            'statut' => $this->statut,
            'delivery_boy_name' => $this->delivery_boy_name,
            'delivery_boy_phone' => $this->delivery_boy_phone,
            'confirmation_cc' => $this->confirmation_cc,
            'mode_paiement' => $this->mode_paiement,
            'total_ht' => $this->total_ht,
            'total_ttc' => $this->total_ttc,
            'devise' => $this->devise ?? 'MAD',
            'notes' => $this->notes,
            'no_answer_count' => $this->no_answer_count,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

            // Status badge info for UI
            'status_badge' => $this->getStatusBadge(),
            
            // Computed type_command based on first article (for backward compatibility)
            'type_command' => $this->whenLoaded('articles', function () {
                $firstArticle = $this->articles->first();
                return $firstArticle ? ($firstArticle->type_command ?? 'order_sample') : 'order_sample';
            }, 'order_sample'),
            
            // Relationships
            'boutique' => $this->whenLoaded('boutique', function () {
                return [
                    'id' => $this->boutique->id,
                    'nom' => $this->boutique->nom,
                    'adresse' => $this->boutique->adresse ?? null,
                ];
            }),

            'client' => $this->whenLoaded('client', function () {
                return [
                    'id' => $this->client->id,
                    'nom_complet' => $this->client->nom_complet,
                    'telephone' => $this->client->telephone,
                    'email' => $this->client->email ?? null,
                ];
            }),

            'client_final' => $this->whenLoaded('clientFinal', function () {
                return [
                    'id' => $this->clientFinal->id,
                    'nom_complet' => $this->clientFinal->nom_complet,
                    'telephone' => $this->clientFinal->telephone,
                    'email' => $this->clientFinal->email ?? null,
                ];
            }),

            'adresse' => $this->whenLoaded('adresse', function () {
                return [
                    'id' => $this->adresse->id,
                    'ville' => $this->adresse->ville,
                    'adresse' => $this->adresse->adresse,
                    'code_postal' => $this->adresse->code_postal ?? null,
                ];
            }),

            'articles' => $this->whenLoaded('articles', function () {
                return $this->articles->map(function ($article) {
                    return [
                        'id' => $article->id,
                        'produit_id' => $article->produit_id,
                        'variante_id' => $article->variante_id,
                        'quantite' => $article->quantite,
                        'prix_unitaire' => $article->prix_unitaire,
                        'remise' => $article->remise,
                        'total_ligne' => $article->total_ligne,
                        'type_command' => $article->type_command ?? 'order_sample',
                        'produit' => $article->produit ? [
                            'id' => $article->produit->id,
                            'titre' => $article->produit->titre,
                            'sku' => $article->produit->sku,
                        ] : null,
                        'variante' => $article->variante ? [
                            'id' => $article->variante->id,
                            'nom' => $article->variante->nom,
                        ] : null,
                    ];
                });
            }),

            'expeditions' => $this->whenLoaded('expeditions', function () {
                return $this->expeditions->map(function ($expedition) {
                    return [
                        'id' => $expedition->id,
                        'tracking_no' => $expedition->tracking_no ?? null,
                        'statut' => $expedition->statut ?? 'preparee',
                        'poids_kg' => $expedition->poids_kg ?? null,
                        'frais_transport' => $expedition->frais_transport ?? 0,
                        'created_at' => $expedition->created_at?->format('Y-m-d H:i:s'),
                        'events' => $expedition->relationLoaded('evenements') ?
                            $expedition->evenements->map(function ($event) {
                                return [
                                    'id' => $event->id,
                                    'code' => $event->code ?? null,
                                    'message' => $event->message ?? null,
                                    'occurred_at' => $event->occured_at?->format('Y-m-d H:i:s'),
                                ];
                            }) : [],
                    ];
                });
            }),

            'commissions' => $this->whenLoaded('commissions', function () {
                return $this->commissions->map(function ($commission) {
                    return [
                        'id' => $commission->id,
                        'type' => $commission->type,
                        'base_amount' => $commission->base_amount ?? 0,
                        'rate' => $commission->rate ?? null,
                        'amount' => $commission->amount ?? 0,
                        'currency' => $commission->currency ?? 'MAD',
                        'status' => $commission->status,
                        'status_badge' => $commission->getStatusBadge(),
                        'eligible_at' => $commission->eligible_at?->format('Y-m-d H:i:s'),
                        'approved_at' => $commission->approved_at?->format('Y-m-d H:i:s'),
                        'paid_at' => $commission->paid_at?->format('Y-m-d H:i:s'),
                        'created_at' => $commission->created_at?->format('Y-m-d H:i:s'),
                    ];
                });
            }),

            'shipping_parcel' => $this->whenLoaded('shippingParcel', function () {
                return [
                    'id' => $this->shippingParcel->id,
                    'tracking_number' => $this->shippingParcel->tracking_number,
                    'status' => $this->shippingParcel->status,
                    'created_at' => $this->shippingParcel->created_at?->format('Y-m-d H:i:s'),
                ];
            }),

            'conflits' => $this->whenLoaded('conflits', function () {
                return $this->conflits->map(function ($conflit) {
                    return [
                        'id' => $conflit->id,
                        'type' => $conflit->type,
                        'score' => $conflit->score,
                        'details' => $conflit->details,
                        'resolu' => $conflit->resolu,
                        'created_at' => $conflit->created_at?->format('Y-m-d H:i:s'),
                    ];
                });
            }),

            'retours' => $this->whenLoaded('retours', function () {
                return $this->retours->map(function ($retour) {
                    return [
                        'id' => $retour->id,
                        'type' => $retour->type,
                        'statut' => $retour->statut,
                        'motif' => $retour->motif,
                        'created_at' => $retour->created_at?->format('Y-m-d H:i:s'),
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
            'en_attente' => ['color' => 'warning', 'text' => 'En attente'],
            'confirmee' => ['color' => 'info', 'text' => 'Confirmée'],
            'expediee' => ['color' => 'primary', 'text' => 'Expédiée'],
            'livree' => ['color' => 'success', 'text' => 'Livrée'],
            'annulee' => ['color' => 'error', 'text' => 'Annulée'],
            'retournee' => ['color' => 'secondary', 'text' => 'Retournée'],
            'returned_to_warehouse' => ['color' => 'info', 'text' => 'Retournée en entrepôt'],
            'echec_livraison' => ['color' => 'error', 'text' => 'Échec livraison'],
            // Additional statuses for safety
            'pending' => ['color' => 'warning', 'text' => 'En attente'],
            'paid' => ['color' => 'success', 'text' => 'Payée'],
            'confirmed' => ['color' => 'info', 'text' => 'Confirmée'],
            'send' => ['color' => 'primary', 'text' => 'Envoyée'],
            'annuler' => ['color' => 'error', 'text' => 'Annulée'],
        ];

        $status = $this->statut ?? 'en_attente';

        return $statusMap[$status] ?? [
            'color' => 'secondary',
            'text' => ucfirst(str_replace('_', ' ', $status))
        ];
    }
}
