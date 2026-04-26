<?php

namespace App\Http\Requests;

use App\Support\Financeiro\FinancialCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinancialReceivableRequest extends FormRequest
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
            'reference' => ['nullable', 'string', 'max:120'],
            'billing_type' => ['nullable', 'string', Rule::in(array_keys(FinancialCatalog::billingTypes()))],
            'client_id' => ['nullable', 'integer', 'exists:client_entities,id'],
            'condominium_id' => ['nullable', 'integer', 'exists:client_condominiums,id'],
            'unit_id' => ['nullable', 'integer', 'exists:client_units,id'],
            'contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'process_id' => ['nullable', 'integer'],
            'category_id' => ['nullable', 'integer', 'exists:financial_categories,id'],
            'cost_center_id' => ['nullable', 'integer', 'exists:financial_cost_centers,id'],
            'account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'original_amount' => ['required', 'string', 'max:40'],
            'interest_amount' => ['nullable', 'string', 'max:40'],
            'penalty_amount' => ['nullable', 'string', 'max:40'],
            'correction_amount' => ['nullable', 'string', 'max:40'],
            'discount_amount' => ['nullable', 'string', 'max:40'],
            'due_date' => ['nullable', 'date'],
            'competence_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', Rule::in(array_keys(FinancialCatalog::paymentMethods()))],
            'status' => ['required', 'string', Rule::in(array_keys(FinancialCatalog::receivableStatuses()))],
            'collection_stage' => ['nullable', 'string', Rule::in(array_keys(FinancialCatalog::collectionStages()))],
            'generate_collection' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
