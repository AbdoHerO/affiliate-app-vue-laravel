<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class PublicProduitResource extends JsonResource
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
            'slug' => $this->slug,
            'titre' => $this->titre,
            'description' => $this->description,
            'copywriting' => $this->copywriting,
            'prix_vente' => $this->prix_vente,
            'prix_affilie' => $this->prix_affilie,
            'rating' => [
                'value' => $this->rating_value,
                'max' => $this->rating_max ?? 5,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Boutique information
            'boutique' => $this->whenLoaded('boutique', function () {
                return [
                    'id' => $this->boutique->id,
                    'nom' => $this->boutique->nom,
                ];
            }),

            // Category information
            'categorie' => $this->whenLoaded('categorie', function () {
                return [
                    'id' => $this->categorie->id,
                    'nom' => $this->categorie->nom,
                ];
            }),

            // Images with full URLs
            'images' => $this->whenLoaded('images', function () {
                return $this->images->map(function ($image) {
                    return [
                        'id' => $image->id,
                        'url' => $this->getFullUrl($image->url),
                        'ordre' => $image->ordre,
                    ];
                });
            }),

            // Videos with full URLs
            'videos' => $this->whenLoaded('videos', function () {
                return $this->videos->map(function ($video) {
                    return [
                        'id' => $video->id,
                        'url' => $this->getFullUrl($video->url),
                        'titre' => $video->titre,
                        'ordre' => $video->ordre,
                    ];
                });
            }),

            // Variants with image URLs
            'variantes' => $this->whenLoaded('variantes', function () {
                return $this->variantes->map(function ($variante) {
                    return [
                        'id' => $variante->id,
                        'nom' => $variante->nom,
                        'valeur' => $variante->valeur,
                        'prix_vente_variante' => $variante->prix_vente_variante,
                        'image_url' => $variante->image_url ? $this->getFullUrl($variante->image_url) : null,
                    ];
                });
            }),

            // Propositions with image URLs (only approved ones)
            'propositions' => $this->whenLoaded('propositions', function () {
                return $this->propositions->map(function ($proposition) {
                    return [
                        'id' => $proposition->id,
                        'titre' => $proposition->titre,
                        'description' => $proposition->description,
                        'type' => $proposition->type,
                        'image_url' => $proposition->image_url ? $this->getFullUrl($proposition->image_url) : null,
                        'created_at' => $proposition->created_at,
                    ];
                });
            }),

            // Stock issues (ruptures) - only active ones
            'ruptures' => $this->whenLoaded('ruptures', function () {
                return $this->ruptures->filter(function ($rupture) {
                    return $rupture->active;
                })->map(function ($rupture) {
                    return [
                        'id' => $rupture->id,
                        'motif' => $rupture->motif,
                        'started_at' => $rupture->started_at,
                        'expected_restock_at' => $rupture->expected_restock_at,
                        'variante_id' => $rupture->variante_id,
                        'created_at' => $rupture->created_at,
                    ];
                });
            }),

            // SEO metadata
            'meta' => [
                'title' => $this->titre,
                'description' => $this->description ? substr(strip_tags($this->description), 0, 160) : null,
                'og_image' => $this->images->first() ? $this->getFullUrl($this->images->first()->url) : null,
            ],
        ];
    }

    /**
     * Get full URL for media files
     */
    private function getFullUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        // If already a full URL, return as is
        if (str_starts_with($path, 'http')) {
            return $path;
        }

        // Convert storage path to full URL
        return Storage::url($path);
    }
}
