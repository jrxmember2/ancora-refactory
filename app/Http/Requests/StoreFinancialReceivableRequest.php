<?php

namespace App\Http\Requests;

use App\Support\Financeiro\FinancialCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'recurrence' => ['nullable', 'string', Rule::in(array_keys(FinancialCatalog::recurrences()))],
            'occurrences' => ['nullable', 'integer', 'min:1', 'max:240'],
            'repeat_until' => ['nullable', 'date', 'after_or_equal:due_date'],
            'import_contract_schedule' => ['nullable', 'boolean'],
            'status' => ['required', 'string', Rule::in(array_keys(FinancialCatalog::receivableStatuses()))],
            'collection_stage' => ['nullable', 'string', Rule::in(array_keys(FinancialCatalog::collectionStages()))],
            'generate_collection' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $importFromContract = $this->boolean('import_contract_schedule');
            $recurrence = trim((string) $this->input('recurrence', ''));
            $isRecurringSeries = !$importFromContract
                && $this->routeIs('financeiro.receivables.store')
                && $recurrence !== ''
                && $recurrence !== 'unica';

            if ($importFromContract && !$this->filled('contract_id')) {
                $validator->errors()->add('contract_id', 'Selecione um contrato para importar a agenda financeira.');
            }

            if ($isRecurringSeries && !$this->filled('due_date')) {
                $validator->errors()->add('due_date', 'Informe o primeiro vencimento para gerar a serie recorrente.');
            }

            if ($isRecurringSeries && !$this->filled('occurrences') && !$this->filled('repeat_until')) {
                $validator->errors()->add('occurrences', 'Informe a quantidade de ocorrencias ou a data final da recorrencia.');
            }
        });
    }
}
