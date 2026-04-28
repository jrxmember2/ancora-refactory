<?php

namespace App\Http\Requests;

use App\Support\Contracts\ContractCatalog;
use App\Support\Financeiro\FinancialCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:60'],
            'title' => ['nullable', 'string', 'max:220'],
            'type' => ['required', 'string', Rule::in(ContractCatalog::types())],
            'category_id' => ['nullable', 'integer', 'exists:contract_categories,id'],
            'template_id' => ['nullable', 'integer', 'exists:contract_templates,id'],
            'client_id' => ['nullable', 'integer', 'exists:client_entities,id'],
            'condominium_id' => ['nullable', 'integer', 'exists:client_condominiums,id'],
            'syndico_entity_id' => ['nullable', 'integer', 'exists:client_entities,id'],
            'unit_id' => ['nullable', 'integer', 'exists:client_units,id'],
            'proposal_id' => ['nullable', 'integer'],
            'process_id' => ['nullable', 'integer'],
            'status' => ['required', 'string', Rule::in(array_keys(ContractCatalog::statuses()))],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'indefinite_term' => ['nullable', 'boolean'],
            'contract_value' => ['nullable', 'string', 'max:40'],
            'monthly_value' => ['nullable', 'string', 'max:40'],
            'total_value' => ['nullable', 'string', 'max:40'],
            'billing_type' => ['nullable', 'string', Rule::in(array_keys(ContractCatalog::billingTypes()))],
            'due_day' => ['nullable', 'integer', 'between:1,31'],
            'recurrence' => ['nullable', 'string', Rule::in(array_keys(ContractCatalog::recurrences()))],
            'adjustment_index' => ['nullable', 'string', 'max:80'],
            'adjustment_periodicity' => ['nullable', 'string', Rule::in(array_keys(ContractCatalog::adjustmentPeriodicities()))],
            'next_adjustment_date' => ['nullable', 'date'],
            'penalty_value' => ['nullable', 'string', 'max:40'],
            'penalty_percentage' => ['nullable', 'string', 'max:20'],
            'generate_financial_entries' => ['nullable', 'boolean'],
            'financial_account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'payment_method' => ['nullable', 'string', Rule::in(array_keys(FinancialCatalog::paymentMethods()))],
            'cost_center_future' => ['nullable', 'string', 'max:120'],
            'financial_category_future' => ['nullable', 'string', 'max:120'],
            'financial_notes' => ['nullable', 'string'],
            'content_html' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'version_notes' => ['nullable', 'string', 'max:255'],
            'generate_pdf_now' => ['nullable', 'boolean'],
        ];
    }
}
