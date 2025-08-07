<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProduitRuptureResource extends JsonResource
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
            'variante_id' => $this->variante_id,
            'actif' => $this->actif,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            
            // Include variant information if loaded
            'variante' => $this->whenLoaded('variante', function () {
                return [
                    'id' => $this->variante->id,
                    'nom' => $this->variante->nom,
                    'valeur' => $this->variante->valeur,
                    'prix_vente_variante' => $this->variante->prix_vente_variante,
                    'sku_variante' => $this->variante->sku_variante,
                    'actif' => $this->variante->actif,
                    
                    // Include product information if loaded
                    'produit' => $this->whenLoaded('produit', function () {
                        return [
                            'id' => $this->variante->produit->id,
                            'titre' => $this->variante->produit->titre,
                            'slug' => $this->variante->produit->slug,
                            'boutique' => $this->when(
                                $this->variante->produit->relationLoaded('boutique'),
                                function () {
                                    return [
                                        'id' => $this->variante->produit->boutique->id,
                                        'nom' => $this->variante->produit->boutique->nom,
                                        'slug' => $this->variante->produit->boutique->slug,
                                    ];
                                }
                            ),
                        ];
                    }),
                ];
            }),
        ];
    }
}
