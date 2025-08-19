<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProduitVarianteResource extends JsonResource
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
            'produit_id' => $this->produit_id,
            'nom' => $this->nom,
            'valeur' => $this->valeur,
            'prix_vente_variante' => $this->prix_vente_variante,
            'sku_variante' => $this->sku_variante,
            'image_url' => $this->image_url ? $this->getFullImageUrl() : null,
            'actif' => $this->actif,
            'stock' => $this->whenLoaded('stocks', function () {
                return $this->stocks->sum('qte_disponible');
            }, 0),
            'attribut_principal' => $this->nom, // For compatibility with ProductDrawer
            'created_at' => $this->when($this->created_at, $this->created_at),
            'updated_at' => $this->when($this->updated_at, $this->updated_at),
            
            // Include product information if loaded
            'produit' => $this->whenLoaded('produit', function () {
                return [
                    'id' => $this->produit->id,
                    'titre' => $this->produit->titre,
                    'slug' => $this->produit->slug,
                ];
            }),
        ];
    }
}
