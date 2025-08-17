<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AttachCommissionsRequest extends FormRequest
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
            'commission_ids' => ['required', 'array', 'min:1'],
            'commission_ids.*' => ['uuid', 'exists:commissions_affilies,id'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'commission_ids.required' => 'Au moins une commission doit être sélectionnée',
            'commission_ids.array' => 'Les IDs de commission doivent être un tableau',
            'commission_ids.min' => 'Au moins une commission doit être sélectionnée',
            'commission_ids.*.exists' => 'Une ou plusieurs commissions sélectionnées n\'existent pas',
        ];
    }
}
