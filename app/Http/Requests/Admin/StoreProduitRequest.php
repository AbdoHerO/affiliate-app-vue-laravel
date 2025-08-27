<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProduitRequest extends FormRequest
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
        return [
            'boutique_id' => 'required|uuid|exists:boutiques,id',
            'categorie_id' => 'nullable|uuid|exists:categories,id',
            'titre' => 'required|string|max:190',
            'sku' => 'nullable|string|max:100|unique:produits,sku',
            'description' => 'nullable|string',
            'copywriting' => 'nullable|string',
            'prix_achat' => 'required|numeric|min:0',
            'prix_vente' => 'required|numeric|min:0.01',
            'prix_affilie' => 'nullable|numeric|min:0',
            'slug' => 'required|string|max:190|unique:produits,slug',
            'actif' => 'required|boolean',
            'quantite_min' => 'nullable|integer|min:1',
            'notes_admin' => 'nullable|string',
            'stock_total' => 'nullable|integer|min:0',
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
            'stock_total.integer' => __('validation.integer', ['attribute' => 'stock total']),
            'stock_total.min' => __('validation.min', ['attribute' => 'stock total', 'min' => 0]),
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
                'slug' => $this->generateUniqueSlug($this->titre)
            ]);
        }
    }

    /**
     * Generate a unique slug from the given title.
     */
    private function generateUniqueSlug(string $titre): string
    {
        $baseSlug = \Illuminate\Support\Str::slug($titre);
        $slug = $baseSlug;
        $counter = 1;

        while (\App\Models\Produit::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
