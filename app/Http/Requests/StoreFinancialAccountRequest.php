<?php

namespace App\Http\Requests;

use App\Support\Financeiro\FinancialCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinancialAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:60'],
            'name' => ['required', 'string', 'max:180'],
            'bank_name' => ['nullable', 'string', 'max:180'],
            'agency' => ['nullable', 'string', 'max:40'],
            'account_number' => ['nullable', 'string', 'max:60'],
            'account_digit' => ['nullable', 'string', 'max:10'],
            'account_type' => ['required', 'string', Rule::in(array_keys(FinancialCatalog::accountTypes()))],
            'pix_key' => ['nullable', 'string', 'max:180'],
            'account_holder' => ['nullable', 'string', 'max:180'],
            'opening_balance' => ['nullable', 'string', 'max:40'],
            'credit_limit' => ['nullable', 'string', 'max:40'],
            'is_primary' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
