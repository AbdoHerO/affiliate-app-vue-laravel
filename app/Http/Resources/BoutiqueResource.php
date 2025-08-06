<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoutiqueResource extends JsonResource
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
            'nom' => $this->nom,
            'slug' => $this->slug,
            'statut' => $this->statut,
            'commission_par_defaut' => $this->commission_par_defaut,
            'email_pro' => $this->email_pro,
            'adresse' => $this->adresse,
            'proprietaire' => $this->whenLoaded('proprietaire', function () {
                return [
                    'id' => $this->proprietaire->id,
                    'nom_complet' => $this->proprietaire->nom_complet,
                    'email' => $this->proprietaire->email,
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
