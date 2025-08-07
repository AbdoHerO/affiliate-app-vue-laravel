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
            'url.required' => __('messages.validation.required', ['attribute' => __('messages.produit_images.url')]),
            'url.string' => __('messages.validation.string', ['attribute' => __('messages.produit_images.url')]),
            'url.max' => __('messages.validation.max.string', ['attribute' => __('messages.produit_images.url'), 'max' => 500]),
            'ordre.integer' => __('messages.validation.integer', ['attribute' => __('messages.produit_images.ordre')]),
            'ordre.min' => __('messages.validation.min.numeric', ['attribute' => __('messages.produit_images.ordre'), 'min' => 0]),
            'items.required' => __('messages.validation.required', ['attribute' => __('messages.produit_images.items')]),
            'items.array' => __('messages.validation.array', ['attribute' => __('messages.produit_images.items')]),
            'items.*.id.required' => __('messages.validation.required', ['attribute' => __('messages.produit_images.id')]),
            'items.*.id.uuid' => __('messages.validation.uuid', ['attribute' => __('messages.produit_images.id')]),
            'items.*.id.exists' => __('messages.validation.exists', ['attribute' => __('messages.produit_images.id')]),
            'items.*.ordre.required' => __('messages.validation.required', ['attribute' => __('messages.produit_images.ordre')]),
            'items.*.ordre.integer' => __('messages.validation.integer', ['attribute' => __('messages.produit_images.ordre')]),
            'items.*.ordre.min' => __('messages.validation.min.numeric', ['attribute' => __('messages.produit_images.ordre'), 'min' => 0]),
        ];
    }
}
