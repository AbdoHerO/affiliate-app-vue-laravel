<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CreateTicketRequest extends FormRequest
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
            'subject' => 'required|string|max:255',
            'category' => 'required|in:general,orders,payments,commissions,kyc,technical,other',
            'priority' => 'required|in:low,normal,high,urgent',
            'requester_id' => 'required|uuid|exists:users,id',
            'assignee_id' => 'nullable|uuid|exists:users,id',
            'relations' => 'sometimes|array',
            'relations.*.related_type' => 'required_with:relations|string',
            'relations.*.related_id' => 'required_with:relations|string',
            'first_message' => 'sometimes|array',
            'first_message.body' => 'required_with:first_message|string',
            'first_message.type' => 'sometimes|in:public,internal',
            'first_message.attachments' => 'sometimes|array|max:5',
            'first_message.attachments.*' => 'file|mimes:pdf,jpg,jpeg,png,gif,doc,docx,txt|max:5120', // 5MB
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'subject.required' => __('messages.validation_required'),
            'subject.max' => __('messages.validation_max_length', ['max' => 255]),
            'category.required' => __('messages.validation_required'),
            'category.in' => __('messages.validation_invalid_choice'),
            'priority.required' => __('messages.validation_required'),
            'priority.in' => __('messages.validation_invalid_choice'),
            'requester_id.required' => __('messages.validation_required'),
            'requester_id.uuid' => __('messages.validation_invalid_format'),
            'requester_id.exists' => __('messages.validation_not_found'),
            'assignee_id.uuid' => __('messages.validation_invalid_format'),
            'assignee_id.exists' => __('messages.validation_not_found'),
            'first_message.body.required_with' => __('messages.validation_required'),
            'first_message.attachments.max' => __('messages.validation_max_files', ['max' => 5]),
            'first_message.attachments.*.file' => __('messages.validation_file'),
            'first_message.attachments.*.mimes' => __('messages.validation_file_type'),
            'first_message.attachments.*.max' => __('messages.validation_file_size', ['max' => '5MB']),
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
            'requester_id' => __('messages.ticket_requester'),
            'assignee_id' => __('messages.ticket_assignee'),
            'first_message.body' => __('messages.message_body'),
            'first_message.attachments' => __('messages.attachments'),
        ];
    }
}
