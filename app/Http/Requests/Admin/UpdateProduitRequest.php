<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProduitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $produitId = $this->route('produit');

        return [
            'boutique_id' => 'required|uuid|exists:boutiques,id',
            'categorie_id' => 'nullable|uuid|exists:categories,id',
            'titre' => 'required|string|max:190',
            'description' => 'nullable|string',
            'copywriting' => 'nullable|string',
            'prix_achat' => 'required|numeric|min:0',
            'prix_vente' => 'required|numeric|min:0.01',
            'prix_affilie' => 'nullable|numeric|min:0',
            'slug' => [
                'required',
                'string',
                'max:190',
                Rule::unique('produits', 'slug')->ignore($produitId)
            ],
            'actif' => 'required|boolean',
            'quantite_min' => 'required|integer|min:1',
            'notes_admin' => 'nullable|string',
            'rating_value' => 'nullable|numeric|min:0|max:5',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'boutique_id' => __('messages.produits.boutique'),
            'categorie_id' => __('messages.produits.categorie'),
            'titre' => __('messages.produits.titre'),
            'description' => __('messages.produits.description'),
            'prix_achat' => __('messages.produits.prix_achat'),
            'prix_vente' => __('messages.produits.prix_vente'),
            'slug' => __('messages.produits.slug'),
            'actif' => __('messages.produits.statut'),
            'quantite_min' => __('messages.produits.quantite_min'),
            'notes_admin' => __('messages.produits.notes_admin'),
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'boutique_id.required' => __('validation.required', ['attribute' => 'boutique']),
            'boutique_id.uuid' => __('validation.uuid', ['attribute' => 'boutique']),
            'boutique_id.exists' => __('validation.exists', ['attribute' => 'boutique']),
            'categorie_id.uuid' => __('validation.uuid', ['attribute' => 'catégorie']),
            'categorie_id.exists' => __('validation.exists', ['attribute' => 'catégorie']),
            'titre.required' => __('validation.required', ['attribute' => 'titre']),
            'titre.string' => __('validation.string', ['attribute' => 'titre']),
            'titre.max' => __('validation.max', ['attribute' => 'titre', 'max' => 190]),
            'description.string' => __('validation.string', ['attribute' => 'description']),
            'prix_achat.required' => __('validation.required', ['attribute' => 'prix d\'achat']),
            'prix_achat.numeric' => __('validation.numeric', ['attribute' => 'prix d\'achat']),
            'prix_achat.min' => __('validation.min', ['attribute' => 'prix d\'achat', 'min' => 0]),
            'prix_vente.required' => __('validation.required', ['attribute' => 'prix de vente']),
            'prix_vente.numeric' => __('validation.numeric', ['attribute' => 'prix de vente']),
            'prix_vente.min' => __('validation.min', ['attribute' => 'prix de vente', 'min' => 0.01]),
            'slug.required' => __('validation.required', ['attribute' => 'slug']),
            'slug.string' => __('validation.string', ['attribute' => 'slug']),
            'slug.max' => __('validation.max', ['attribute' => 'slug', 'max' => 190]),
            'slug.unique' => __('validation.unique', ['attribute' => 'slug']),
            'actif.required' => __('validation.required', ['attribute' => 'statut']),
            'actif.boolean' => __('validation.boolean', ['attribute' => 'statut']),
            'quantite_min.integer' => __('validation.integer', ['attribute' => 'quantité minimale']),
            'quantite_min.min' => __('validation.min', ['attribute' => 'quantité minimale', 'min' => 1]),
            'notes_admin.string' => __('validation.string', ['attribute' => 'notes admin']),
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Auto-generate slug from titre if empty
        if (empty($this->slug) && !empty($this->titre)) {
            $this->merge([
                'slug' => $this->generateUniqueSlug($this->titre, $this->route('produit'))
            ]);
        }
    }

    /**
     * Generate a unique slug from the given title.
     */
    private function generateUniqueSlug(string $titre, ?string $ignoreId = null): string
    {
        $baseSlug = \Illuminate\Support\Str::slug($titre);
        $slug = $baseSlug;
        $counter = 1;

        $query = \App\Models\Produit::where('slug', $slug);
        
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        while ($query->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
            
            $query = \App\Models\Produit::where('slug', $slug);
            if ($ignoreId) {
                $query->where('id', '!=', $ignoreId);
            }
        }

        return $slug;
    }
}
