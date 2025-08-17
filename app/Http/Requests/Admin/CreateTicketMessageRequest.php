<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateTicketMessageRequest extends FormRequest
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
            'type' => 'required|in:public,internal',
            'body' => 'required|string',
            'attachments' => 'sometimes|array|max:5',
            'attachments.*' => 'file|mimes:pdf,jpg,jpeg,png,gif,doc,docx,txt|max:5120', // 5MB
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'type.required' => __('messages.validation_required'),
            'type.in' => __('messages.validation_invalid_choice'),
            'body.required' => __('messages.validation_required'),
            'attachments.max' => __('messages.validation_max_files', ['max' => 5]),
            'attachments.*.file' => __('messages.validation_file'),
            'attachments.*.mimes' => __('messages.validation_file_type'),
            'attachments.*.max' => __('messages.validation_file_size', ['max' => '5MB']),
        ];
    }

    /**
     * Get custom attribute names.
     */
    public function attributes(): array
    {
        return [
            'type' => __('messages.message_type'),
            'body' => __('messages.message_body'),
            'attachments' => __('messages.attachments'),
        ];
    }
}
