<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MarkPaidRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'payment_ref' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date'],
            'evidence' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], // 5MB max
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'payment_ref.max' => 'La référence de paiement ne peut pas dépasser 100 caractères',
            'paid_at.date' => 'La date de paiement doit être une date valide',
            'evidence.file' => 'La preuve doit être un fichier',
            'evidence.mimes' => 'La preuve doit être un fichier PDF, JPG, JPEG ou PNG',
            'evidence.max' => 'La preuve ne peut pas dépasser 5MB',
        ];
    }
}
