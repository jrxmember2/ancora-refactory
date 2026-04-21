<?php

namespace App\Http\Requests\Internal\Automation;

use Illuminate\Foundation\Http\FormRequest;

class ProcessWhatsappMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'channel' => ['required', 'string', 'max:30'],
            'provider' => ['required', 'string', 'max:30'],
            'phone' => ['required', 'string', 'max:40'],
            'external_contact_id' => ['nullable', 'string', 'max:120'],
            'external_message_id' => ['nullable', 'string', 'max:120'],
            'message_text' => ['required', 'string', 'max:4000'],
            'timestamp' => ['nullable', 'string', 'max:40'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
