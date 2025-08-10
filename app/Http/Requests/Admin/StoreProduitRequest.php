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
            'description' => 'nullable|string',
            'prix_achat' => 'required|numeric|min:0',
            'prix_vente' => 'required|numeric|min:0.01',
            'prix_affilie' => 'nullable|numeric|min:0',
            'slug' => 'required|string|max:190|unique:produits,slug',
            'actif' => 'required|boolean',
            'quantite_min' => 'nullable|integer|min:1',
            'notes_admin' => 'nullable|string',
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
            'boutique_id.required' => __('messages.validation.required', ['attribute' => __('messages.produits.boutique')]),
            'boutique_id.uuid' => __('messages.validation.uuid', ['attribute' => __('messages.produits.boutique')]),
            'boutique_id.exists' => __('messages.validation.exists', ['attribute' => __('messages.produits.boutique')]),
            'categorie_id.uuid' => __('messages.validation.uuid', ['attribute' => __('messages.produits.categorie')]),
            'categorie_id.exists' => __('messages.validation.exists', ['attribute' => __('messages.produits.categorie')]),
            'titre.required' => __('messages.validation.required', ['attribute' => __('messages.produits.titre')]),
            'titre.string' => __('messages.validation.string', ['attribute' => __('messages.produits.titre')]),
            'titre.max' => __('messages.validation.max.string', ['attribute' => __('messages.produits.titre'), 'max' => 190]),
            'description.string' => __('messages.validation.string', ['attribute' => __('messages.produits.description')]),
            'prix_achat.required' => __('messages.validation.required', ['attribute' => __('messages.produits.prix_achat')]),
            'prix_achat.numeric' => __('messages.validation.numeric', ['attribute' => __('messages.produits.prix_achat')]),
            'prix_achat.min' => __('messages.validation.min.numeric', ['attribute' => __('messages.produits.prix_achat'), 'min' => 0]),
            'prix_vente.required' => __('messages.validation.required', ['attribute' => __('messages.produits.prix_vente')]),
            'prix_vente.numeric' => __('messages.validation.numeric', ['attribute' => __('messages.produits.prix_vente')]),
            'prix_vente.min' => __('messages.validation.min.numeric', ['attribute' => __('messages.produits.prix_vente'), 'min' => 0.01]),
            'slug.required' => __('messages.validation.required', ['attribute' => __('messages.produits.slug')]),
            'slug.string' => __('messages.validation.string', ['attribute' => __('messages.produits.slug')]),
            'slug.max' => __('messages.validation.max.string', ['attribute' => __('messages.produits.slug'), 'max' => 190]),
            'slug.unique' => __('messages.validation.unique', ['attribute' => __('messages.produits.slug')]),
            'actif.required' => __('messages.validation.required', ['attribute' => __('messages.produits.statut')]),
            'actif.boolean' => __('messages.validation.boolean', ['attribute' => __('messages.produits.statut')]),
            'quantite_min.integer' => __('messages.validation.integer', ['attribute' => __('messages.produits.quantite_min')]),
            'quantite_min.min' => __('messages.validation.min.numeric', ['attribute' => __('messages.produits.quantite_min'), 'min' => 1]),
            'notes_admin.string' => __('messages.validation.string', ['attribute' => __('messages.produits.notes_admin')]),
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
