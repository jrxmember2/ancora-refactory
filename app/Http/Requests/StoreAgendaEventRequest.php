<?php

namespace App\Http\Requests;

use App\Support\Agenda\AgendaCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgendaEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:220'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'string', Rule::in(array_keys(AgendaCatalog::types()))],
            'status' => ['nullable', 'string', Rule::in(array_keys(AgendaCatalog::statuses()))],
            'priority' => ['nullable', 'string', Rule::in(array_keys(AgendaCatalog::priorities()))],
            'apply_color' => ['nullable', 'boolean'],
            'color' => ['nullable', 'string', 'regex:/^#?[0-9a-fA-F]{6}$/'],
            'is_fatal' => ['nullable', 'boolean'],
            'all_day' => ['nullable', 'boolean'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'recurrence' => ['nullable', 'string', Rule::in(array_keys(AgendaCatalog::recurrences()))],
            'recurrence_until' => ['nullable', 'date', 'after_or_equal:start_at'],
            'participants' => ['nullable', 'array'],
            'participants.*' => ['integer', 'exists:users,id'],
            'location' => ['nullable', 'string', 'max:220'],
            'reminder_minutes' => ['nullable', 'integer', 'min:0', 'max:43200'],
            'reminders' => ['nullable', 'array'],
            'reminders.*' => ['integer', 'min:1', 'max:43200'],
            'remind_email' => ['nullable', 'boolean'],
            'remind_whatsapp' => ['nullable', 'boolean'],
            'copy_enabled' => ['nullable', 'boolean'],
            'copy_name' => ['nullable', 'string', 'max:160'],
            'copy_phone' => ['nullable', 'string', 'max:30'],
            'copy_email' => ['nullable', 'email', 'max:190'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'requester_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'process_id' => ['nullable', 'integer', 'exists:process_cases,id'],
            'demand_id' => ['nullable', 'integer', 'exists:demands,id'],
            'client_id' => ['nullable', 'integer', 'exists:client_entities,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
        ];
    }
}
