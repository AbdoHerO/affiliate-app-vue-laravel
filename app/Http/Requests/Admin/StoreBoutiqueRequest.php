<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreBoutiqueRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole('admin');
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Auto-generate slug from nom if not provided
        if (empty($this->slug) && !empty($this->nom)) {
            $baseSlug = Str::slug($this->nom);
            $slug = $baseSlug;
            $counter = 1;
            
            // Ensure uniqueness
            while (\App\Models\Boutique::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter;
                $counter++;
            }
            
            $this->merge(['slug' => $slug]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nom' => 'required|string|max:150',
            'slug' => 'required|string|max:160|unique:boutiques,slug',
            'proprietaire_id' => 'required|uuid|exists:users,id',
            'email_pro' => 'nullable|email|max:190',
            'adresse' => 'nullable|string',
            'statut' => 'required|in:actif,suspendu,desactive',
            'commission_par_defaut' => 'nullable|numeric|min:0|max:100',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nom' => __('admin.boutique.form.name'),
            'slug' => __('admin.boutique.form.slug'),
            'proprietaire_id' => __('admin.boutique.form.owner'),
            'email_pro' => __('admin.boutique.form.emailPro'),
            'adresse' => __('admin.boutique.form.address'),
            'statut' => __('admin.boutique.form.status'),
            'commission_par_defaut' => __('admin.boutique.form.commissionDefault'),
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nom.required' => __('validation.required', ['attribute' => __('admin.boutique.form.name')]),
            'slug.unique' => __('validation.unique', ['attribute' => __('admin.boutique.form.slug')]),
            'proprietaire_id.exists' => __('validation.exists', ['attribute' => __('admin.boutique.form.owner')]),
            'email_pro.email' => __('validation.email', ['attribute' => __('admin.boutique.form.emailPro')]),
            'statut.in' => __('validation.in', ['attribute' => __('admin.boutique.form.status')]),
            'commission_par_defaut.numeric' => __('validation.numeric', ['attribute' => __('admin.boutique.form.commissionDefault')]),
            'commission_par_defaut.min' => __('validation.min.numeric', ['attribute' => __('admin.boutique.form.commissionDefault'), 'min' => 0]),
            'commission_par_defaut.max' => __('validation.max.numeric', ['attribute' => __('admin.boutique.form.commissionDefault'), 'max' => 100]),
        ];
    }
}
