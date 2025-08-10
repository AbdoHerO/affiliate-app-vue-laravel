<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProduitImageRequest extends FormRequest
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
        // For bulk sort operations
        if ($this->has('items')) {
            return [
                'items' => 'required|array',
                'items.*.id' => 'required|uuid|exists:produit_images,id',
                'items.*.ordre' => 'required|integer|min:0',
            ];
        }

        // For single image creation
        return [
            'url' => 'required|string|max:500',
            'ordre' => 'nullable|integer|min:0',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'url' => __('messages.produit_images.url'),
            'ordre' => __('messages.produit_images.ordre'),
            'items' => __('messages.produit_images.items'),
            'items.*.id' => __('messages.produit_images.id'),
            'items.*.ordre' => __('messages.produit_images.ordre'),
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'url.required' => __('validation.required', ['attribute' => 'URL']),
            'url.string' => __('validation.string', ['attribute' => 'URL']),
            'url.max' => __('validation.max', ['attribute' => 'URL', 'max' => 500]),
            'ordre.integer' => __('validation.integer', ['attribute' => 'ordre']),
            'ordre.min' => __('validation.min', ['attribute' => 'ordre', 'min' => 0]),
            'items.required' => __('validation.required', ['attribute' => 'éléments']),
            'items.array' => __('validation.array', ['attribute' => 'éléments']),
            'items.*.id.required' => __('validation.required', ['attribute' => 'ID']),
            'items.*.id.uuid' => __('validation.uuid', ['attribute' => 'ID']),
            'items.*.id.exists' => __('validation.exists', ['attribute' => 'ID']),
            'items.*.ordre.required' => __('validation.required', ['attribute' => 'ordre']),
            'items.*.ordre.integer' => __('validation.integer', ['attribute' => 'ordre']),
            'items.*.ordre.min' => __('validation.min', ['attribute' => 'ordre', 'min' => 0]),
        ];
    }
}
