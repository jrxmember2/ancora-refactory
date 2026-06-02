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
            'is_fatal' => ['nullable', 'boolean'],
            'all_day' => ['nullable', 'boolean'],
            'start_at' => ['required', 'date'],
            'end_at' => ['nullable', 'date', 'after_or_equal:start_at'],
            'location' => ['nullable', 'string', 'max:220'],
            'reminder_minutes' => ['nullable', 'integer', 'min:0', 'max:43200'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'requester_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'process_id' => ['nullable', 'integer', 'exists:process_cases,id'],
            'demand_id' => ['nullable', 'integer', 'exists:demands,id'],
            'client_id' => ['nullable', 'integer', 'exists:client_entities,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
        ];
    }
}
