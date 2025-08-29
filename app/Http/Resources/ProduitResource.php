<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProduitResource extends JsonResource
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
            'categorie_id' => $this->categorie_id,
            'titre' => $this->titre,
            'sku' => $this->sku,
            'description' => $this->description,
            'copywriting' => $this->copywriting,
            'prix_achat' => $this->prix_achat,
            'prix_vente' => $this->prix_vente,
            'prix_affilie' => $this->prix_affilie,
            'slug' => $this->slug,
            'actif' => $this->actif,
            'quantite_min' => $this->quantite_min,
            'stock_total' => $this->stock_total,
            'stock_fake' => $this->stock_fake,
            'notes_admin' => $this->notes_admin,
            'rating_value' => $this->rating_value,
            'rating' => [
                'value' => $this->rating_value,
                'max' => $this->rating_max ?? 5,
                'updated_by' => $this->ratingUpdater?->nom_complet,
                'updated_at' => optional($this->rating_updated_at)->toISOString(),
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Relations
            'boutique' => $this->whenLoaded('boutique', function () {
                return [
                    'id' => $this->boutique->id,
                    'nom' => $this->boutique->nom,
                ];
            }),
            
            'categorie' => $this->whenLoaded('categorie', function () {
                return [
                    'id' => $this->categorie->id,
                    'nom' => $this->categorie->nom,
                ];
            }),
            
            // Images
            'images' => ProduitImageResource::collection($this->whenLoaded('images')),

            // Videos
            'videos' => ProduitVideoResource::collection($this->whenLoaded('videos')),

            // Variants
            'variantes' => ProduitVarianteResource::collection($this->whenLoaded('variantes')),

            // Propositions
            'propositions' => $this->whenLoaded('propositions', function () {
                return $this->propositions->map(function ($proposition) {
                    return [
                        'id' => $proposition->id,
                        'titre' => $proposition->titre,
                        'description' => $proposition->description,
                        'type' => $proposition->type,
                        'statut' => $proposition->statut,
                        'image_url' => $proposition->image_url ? $proposition->getFullImageUrl() : null,
                        'auteur' => $proposition->relationLoaded('auteur') ? [
                            'id' => $proposition->auteur->id,
                            'nom_complet' => $proposition->auteur->nom_complet,
                            'email' => $proposition->auteur->email,
                        ] : null,
                        'created_at' => $proposition->created_at,
                        'updated_at' => $proposition->updated_at,
                    ];
                });
            }),

            // Ruptures
            'ruptures' => $this->whenLoaded('ruptures', function () {
                return $this->ruptures->map(function ($rupture) {
                    return [
                        'id' => $rupture->id,
                        'variante_id' => $rupture->variante_id,
                        'motif' => $rupture->motif,
                        'started_at' => $rupture->started_at,
                        'expected_restock_at' => $rupture->expected_restock_at,
                        'active' => $rupture->active,
                        'resolved_at' => $rupture->resolved_at,
                        'created_at' => $rupture->created_at,
                        'updated_at' => $rupture->updated_at,
                    ];
                });
            }),
        ];
    }
}
