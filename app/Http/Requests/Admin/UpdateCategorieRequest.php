<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategorieRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $categorieId = $this->route('categorie');

        return [
            'nom' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => [
                'sometimes', 
                'nullable', 
                'string', 
                'max:255', 
                Rule::unique('categories', 'slug')->ignore($categorieId)
            ],
            'image_url' => ['sometimes', 'nullable', 'string', 'max:500'],
            'ordre' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'actif' => ['sometimes', 'boolean']
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
            'nom' => __('messages.category_name'),
            'slug' => __('messages.category_slug'),
            'image_url' => __('messages.category_image'),
            'ordre' => __('messages.category_order'),
            'actif' => __('messages.category_status')
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
            'nom.required' => __('messages.category_name_required'),
            'nom.string' => __('messages.category_name_string'),
            'nom.max' => __('messages.category_name_max'),
            'slug.string' => __('messages.category_slug_string'),
            'slug.max' => __('messages.category_slug_max'),
            'slug.unique' => __('messages.category_slug_unique'),
            'image_url.string' => __('messages.category_image_string'),
            'image_url.max' => __('messages.category_image_max'),
            'ordre.integer' => __('messages.category_order_integer'),
            'ordre.min' => __('messages.category_order_min'),
            'actif.boolean' => __('messages.category_status_boolean')
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('actif') && is_string($this->actif)) {
            $this->merge([
                'actif' => filter_var($this->actif, FILTER_VALIDATE_BOOLEAN)
            ]);
        }

        if ($this->has('ordre') && is_string($this->ordre)) {
            $this->merge([
                'ordre' => (int) $this->ordre
            ]);
        }
    }
}
