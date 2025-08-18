<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateStockMovementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasRole('admin');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'produit_id' => 'required|uuid|exists:produits,id',
            'variante_id' => 'nullable|uuid|exists:produit_variantes,id',
            'entrepot_id' => 'nullable|uuid|exists:entrepots,id',
            'type' => 'required|in:in,out,adjust',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|in:purchase,correction,return,damage,manual,delivery_return,cancel',
            'note' => 'nullable|string|max:500',
            'reference' => 'nullable|string|max:100',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'produit_id.required' => __('messages.validation_required'),
            'produit_id.uuid' => __('messages.validation_uuid'),
            'produit_id.exists' => __('messages.validation_exists'),
            'variante_id.uuid' => __('messages.validation_uuid'),
            'variante_id.exists' => __('messages.validation_exists'),
            'entrepot_id.uuid' => __('messages.validation_uuid'),
            'entrepot_id.exists' => __('messages.validation_exists'),
            'type.required' => __('messages.validation_required'),
            'type.in' => __('messages.validation_invalid_choice'),
            'quantity.required' => __('messages.validation_required'),
            'quantity.integer' => __('messages.validation_integer'),
            'quantity.min' => __('messages.validation_min_value', ['min' => 1]),
            'reason.required' => __('messages.validation_required'),
            'reason.in' => __('messages.validation_invalid_choice'),
            'note.max' => __('messages.validation_max_length', ['max' => 500]),
            'reference.max' => __('messages.validation_max_length', ['max' => 100]),
        ];
    }

    /**
     * Get custom attribute names.
     */
    public function attributes(): array
    {
        return [
            'produit_id' => __('messages.product'),
            'variante_id' => __('messages.variant'),
            'entrepot_id' => __('messages.warehouse'),
            'type' => __('messages.movement_type'),
            'quantity' => __('messages.quantity'),
            'reason' => __('messages.reason'),
            'note' => __('messages.note'),
            'reference' => __('messages.reference'),
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // If no variante_id provided, we'll handle product-level stock
        // If no entrepot_id provided, we'll use the default warehouse for the product's boutique
    }
}
