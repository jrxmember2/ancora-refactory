<?php

namespace App\Http\Requests;

use App\Support\Financeiro\FinancialCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinancialPayableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:60'],
            'title' => ['required', 'string', 'max:220'],
            'supplier_entity_id' => ['nullable', 'integer', 'exists:client_entities,id'],
            'supplier_name_snapshot' => ['nullable', 'string', 'max:220'],
            'category_id' => ['nullable', 'integer', 'exists:financial_categories,id'],
            'cost_center_id' => ['nullable', 'integer', 'exists:financial_cost_centers,id'],
            'account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'process_id' => ['nullable', 'integer'],
            'amount' => ['required', 'string', 'max:40'],
            'due_date' => ['nullable', 'date'],
            'competence_date' => ['nullable', 'date'],
            'status' => ['required', 'string', Rule::in(array_keys(FinancialCatalog::payableStatuses()))],
            'payment_method' => ['nullable', 'string', Rule::in(array_keys(FinancialCatalog::paymentMethods()))],
            'recurrence' => ['nullable', 'string', Rule::in(array_keys(FinancialCatalog::recurrences()))],
            'occurrences' => ['nullable', 'integer', 'min:1', 'max:240'],
            'repeat_until' => ['nullable', 'date', 'after_or_equal:due_date'],
            'notes' => ['nullable', 'string'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
