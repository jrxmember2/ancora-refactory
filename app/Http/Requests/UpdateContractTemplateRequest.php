<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateContractTemplateRequest extends StoreContractTemplateRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $templateId = $this->route('template')?->id ?? $this->route('template');

        $rules['name'] = ['required', 'string', 'max:180', Rule::unique('contract_templates', 'name')->ignore($templateId)];

        return $rules;
    }
}
