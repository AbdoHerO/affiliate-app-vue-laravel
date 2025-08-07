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
            'description' => $this->description,
            'prix_achat' => $this->prix_achat,
            'prix_vente' => $this->prix_vente,
            'slug' => $this->slug,
            'actif' => $this->actif,
            'quantite_min' => $this->quantite_min,
            'notes_admin' => $this->notes_admin,
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
        ];
    }
}
