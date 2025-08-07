<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProduitVideoResource extends JsonResource
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
            'url' => $this->url,
            'titre' => $this->titre,
            'ordre' => $this->ordre,
            
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
