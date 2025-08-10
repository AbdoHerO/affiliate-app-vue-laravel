<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreProduitRuptureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'variante_id' => 'nullable|uuid|exists:produit_variantes,id',
            'motif' => 'required|string|max:500',
            'started_at' => 'required|date',
            'expected_restock_at' => 'nullable|date|after_or_equal:started_at',
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
            'variante_id' => 'Variant',
            'motif' => 'Reason',
            'started_at' => 'Started At',
            'expected_restock_at' => 'Expected Restock',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'variante_id.exists' => 'The selected variant is invalid.',
            'motif.required' => 'The reason field is required.',
            'motif.max' => 'The reason may not be greater than 255 characters.',
            'started_at.required' => 'The started at field is required.',
            'started_at.date' => 'The started at must be a valid date.',
            'expected_restock_at.date' => 'The expected restock must be a valid date.',
            'expected_restock_at.after' => 'The expected restock must be after the started date.',
        ];
    }
}
