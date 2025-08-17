<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Withdrawal;

class CreateWithdrawalRequest extends FormRequest
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
            'user_id' => ['required', 'uuid', 'exists:users,id'],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'method' => ['nullable', 'string', 'in:' . implode(',', Withdrawal::getMethods())],
            'notes' => ['nullable', 'string', 'max:1000'],
            'commission_ids' => ['nullable', 'array'],
            'commission_ids.*' => ['uuid', 'exists:commissions_affilies,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'user_id.required' => 'L\'utilisateur est requis',
            'user_id.exists' => 'L\'utilisateur sélectionné n\'existe pas',
            'amount.numeric' => 'Le montant doit être un nombre',
            'amount.min' => 'Le montant doit être positif',
            'amount.max' => 'Le montant ne peut pas dépasser 999,999.99',
            'method.in' => 'La méthode de retrait sélectionnée n\'est pas valide',
            'commission_ids.array' => 'Les IDs de commission doivent être un tableau',
            'commission_ids.*.exists' => 'Une ou plusieurs commissions sélectionnées n\'existent pas',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate that either amount or commission_ids is provided
            if (!$this->amount && empty($this->commission_ids)) {
                $validator->errors()->add('amount', 'Le montant ou les commissions doivent être spécifiés');
            }
        });
    }
}
