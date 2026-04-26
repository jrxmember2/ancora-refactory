<?php

namespace App\Http\Requests;

use App\Support\Contracts\ContractCatalog;
use App\Support\Contracts\ContractVariableCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180', 'unique:contract_templates,name'],
            'document_type' => ['required', 'string', Rule::in(ContractCatalog::types())],
            'category_id' => ['nullable', 'integer', 'exists:contract_categories,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'content_html' => ['nullable', 'string'],
            'header_html' => ['nullable', 'string'],
            'footer_html' => ['nullable', 'string'],
            'page_orientation' => ['required', 'string', Rule::in(array_keys(ContractCatalog::pageOrientations()))],
            'margin_top' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'margin_right' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'margin_bottom' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'margin_left' => ['nullable', 'numeric', 'min:0', 'max:10'],
            'available_variables' => ['nullable', 'array'],
            'available_variables.*' => ['string', Rule::in(ContractVariableCatalog::keys())],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
