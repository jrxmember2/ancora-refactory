<?php

namespace App\Services;

use App\Models\ClientEntity;
use App\Support\ContractSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SignatureSignerService
{
    public function normalizeSigners(Request $request): array
    {
        $rows = collect($request->input('signers', []))
            ->map(function ($row) {
                $row = is_array($row) ? $row : [];

                $phone = trim((string) ($row['phone'] ?? ''));
                $document = trim((string) ($row['document_number'] ?? ''));
                $order = trim((string) ($row['order_index'] ?? ''));

                return [
                    'name' => trim((string) ($row['name'] ?? '')),
                    'email' => trim((string) ($row['email'] ?? '')),
                    'phone' => $this->formatPhone($phone),
                    'phone_digits' => $this->digits($phone),
                    'document_number' => $this->formatDocument($document),
                    'document_digits' => $this->digits($document),
                    'role_label' => trim((string) ($row['role_label'] ?? '')),
                    'order_index' => $order !== '' ? max(1, (int) $order) : null,
                ];
            })
            ->filter(fn (array $row) => collect($row)->except('order_index')->filter(fn ($value) => $value !== '')->isNotEmpty())
            ->values();

        if ($rows->isEmpty()) {
            throw ValidationException::withMessages([
                'signers' => 'Informe ao menos um signatario.',
            ]);
        }

        $errors = [];
        foreach ($rows as $index => $row) {
            $line = $index + 1;

            if ($row['name'] === '') {
                $errors['signers.' . $index . '.name'] = 'Informe o nome do signatario na linha ' . $line . '.';
            }

            if ($row['email'] === '') {
                $errors['signers.' . $index . '.email'] = 'Informe o e-mail do signatario na linha ' . $line . '.';
            } elseif (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['signers.' . $index . '.email'] = 'Informe um e-mail valido na linha ' . $line . '.';
            }

            if ($row['phone_digits'] !== '' && !in_array(strlen($row['phone_digits']), [10, 11, 12, 13], true)) {
                $errors['signers.' . $index . '.phone'] = 'Informe um telefone celular valido na linha ' . $line . '.';
            }

            if ($row['document_digits'] !== '' && !in_array(strlen($row['document_digits']), [11, 14], true)) {
                $errors['signers.' . $index . '.document_number'] = 'Informe um CPF ou CNPJ valido na linha ' . $line . '.';
            }

            if ($row['role_label'] === '') {
                $errors['signers.' . $index . '.role_label'] = 'Selecione o papel no documento na linha ' . $line . '.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $ordered = $rows
            ->map(function (array $row, int $index) {
                unset($row['phone_digits'], $row['document_digits']);
                $row['order_index'] = $row['order_index'] ?: ($index + 1);

                return $row;
            })
            ->sortBy('order_index')
            ->values()
            ->map(function (array $row, int $index) {
                $row['order_index'] = $index + 1;

                return $row;
            });

        return $ordered->all();
    }

    public function defaultSignerOptions(): array
    {
        return collect(ContractSettings::jsonArray('assinafy_default_signers_json'))
            ->map(function ($row) {
                $row = is_array($row) ? $row : [];

                return [
                    'name' => trim((string) ($row['name'] ?? '')),
                    'email' => trim((string) ($row['email'] ?? '')),
                    'phone' => $this->formatPhone((string) ($row['phone'] ?? '')),
                    'document_number' => $this->formatDocument((string) ($row['document_number'] ?? '')),
                    'role_label' => trim((string) ($row['role_label'] ?? '')),
                    'order_index' => isset($row['order_index']) ? max(1, (int) $row['order_index']) : null,
                ];
            })
            ->filter(fn (array $row) => $row['name'] !== '' || $row['email'] !== '')
            ->values()
            ->all();
    }

    public function signerFromEntity(ClientEntity $entity, string $roleLabel): array
    {
        return [
            'name' => trim((string) ($entity->display_name ?: $entity->legal_name ?: '')),
            'email' => trim((string) collect($entity->emails_json ?? [])->pluck('email')->filter()->first()),
            'phone' => $this->formatPhone((string) collect($entity->phones_json ?? [])->pluck('number')->filter()->first()),
            'document_number' => $this->formatDocument((string) ($entity->cpf_cnpj ?: '')),
            'role_label' => $roleLabel,
            'order_index' => null,
        ];
    }

    public function blankSigner(string $roleLabel = 'Signatario'): array
    {
        return [
            'name' => '',
            'email' => '',
            'phone' => '',
            'document_number' => '',
            'role_label' => $roleLabel,
            'order_index' => null,
        ];
    }

    public function uniqueSigners(Collection $rows): Collection
    {
        return $rows
            ->filter()
            ->unique(fn (array $row) => Str::lower((string) ($row['email'] ?? '')) . '|' . Str::lower((string) ($row['name'] ?? '')))
            ->values();
    }

    public function formatPhone(string $value): string
    {
        $digits = $this->digits($value);
        if ($digits === '') {
            return '';
        }

        if (strlen($digits) >= 12 && str_starts_with($digits, '55')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digits) ?: $digits;
        }

        if (strlen($digits) === 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digits) ?: $digits;
        }

        return $digits;
    }

    public function formatDocument(string $value): string
    {
        $digits = $this->digits($value);
        if ($digits === '') {
            return '';
        }

        if (strlen($digits) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits) ?: $digits;
        }

        if (strlen($digits) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits) ?: $digits;
        }

        return $digits;
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?: '';
    }
}
