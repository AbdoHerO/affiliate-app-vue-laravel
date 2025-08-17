<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketRequest extends FormRequest
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
            'subject' => 'sometimes|string|max:255',
            'category' => 'sometimes|in:general,orders,payments,commissions,kyc,technical,other',
            'priority' => 'sometimes|in:low,normal,high,urgent',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'subject.max' => __('messages.validation_max_length', ['max' => 255]),
            'category.in' => __('messages.validation_invalid_choice'),
            'priority.in' => __('messages.validation_invalid_choice'),
        ];
    }

    /**
     * Get custom attribute names.
     */
    public function attributes(): array
    {
        return [
            'subject' => __('messages.ticket_subject'),
            'category' => __('messages.ticket_category'),
            'priority' => __('messages.ticket_priority'),
        ];
    }
}
