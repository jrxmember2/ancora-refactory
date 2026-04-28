<?php

namespace App\Services;

use App\Models\ClientCondominium;
use App\Models\ClientEntity;
use App\Models\ClientUnit;
use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\Proposal;
use App\Models\User;
use App\Support\AncoraSettings;
use App\Support\ContractSettings;
use Carbon\Carbon;

class ContractRenderService
{
    public function draftVariables(array $attributes): array
    {
        $client = !empty($attributes['client_id']) ? ClientEntity::query()->find((int) $attributes['client_id']) : null;
        $condominium = !empty($attributes['condominium_id']) ? ClientCondominium::query()->find((int) $attributes['condominium_id']) : null;
        $unit = !empty($attributes['unit_id']) ? ClientUnit::query()->with(['block', 'condominium', 'owner'])->find((int) $attributes['unit_id']) : null;
        $responsible = !empty($attributes['responsible_user_id']) ? User::query()->find((int) $attributes['responsible_user_id']) : null;

        if (!$condominium && $unit?->condominium) {
            $condominium = $unit->condominium;
        }

        if (!$client && $unit?->owner) {
            $client = $unit->owner;
        }

        return $this->composeVariables($attributes, $client, $condominium, $unit, $responsible);
    }

    public function contractVariables(Contract $contract): array
    {
        $contract->loadMissing([
            'client',
            'condominium.syndic',
            'unit.block',
            'unit.owner',
            'responsible',
        ]);

        return $this->composeVariables($contract->toArray(), $contract->client, $contract->condominium, $contract->unit, $contract->responsible, $contract);
    }

    public function renderTemplate(?ContractTemplate $template, array $attributes, ?string $overrideHtml = null): string
    {
        $base = $overrideHtml;
        if ($base === null || trim($base) === '') {
            $base = $template?->content_html ?: '';
        }

        return $this->renderHtml($base, $this->draftVariables($attributes));
    }

    public function renderHtml(string $html, array $variables): string
    {
        $map = [];
        foreach ($variables as $key => $value) {
            $map['{{' . $key . '}}'] = (string) $value;
        }

        return strtr($html, $map);
    }

    public function documentPayload(Contract $contract, ?string $contentHtml = null): array
    {
        $variables = $this->contractVariables($contract);
        $contract->loadMissing(['category', 'template', 'client', 'condominium.syndic', 'unit.block', 'responsible', 'creator']);

        $city = trim((string) ($variables['cidade'] ?? ''));
        $state = trim((string) ContractSettings::get('default_state', 'ES'));
        $signatureText = trim((string) ContractSettings::get('signature_text', '________________________________________'));
        $dateLong = Carbon::now()->locale('pt_BR')->translatedFormat('d \\d\\e F \\d\\e Y');

        return [
            'brand' => AncoraSettings::brand(),
            'settings' => [
                'city' => $city,
                'state' => $state,
                'footer_text' => ContractSettings::get('footer_text', 'Documento gerado pelo sistema Âncora.'),
                'show_logo' => ContractSettings::bool('show_logo', true),
                'signature_text' => $signatureText,
            ],
            'contract' => $contract,
            'variables' => $variables,
            'content_html' => $contentHtml !== null && trim($contentHtml) !== ''
                ? $this->renderHtml($contentHtml, $variables)
                : $this->renderHtml((string) $contract->content_html, $variables),
            'date_long' => $dateLong,
            'location_label' => trim($city . ($state !== '' ? '/' . strtolower($state) : '')),
            'client_label' => $contract->client?->display_name ?: ($contract->condominium?->name ?: 'Não informado'),
            'condominium_label' => $contract->condominium?->name,
            'unit_label' => $contract->unit?->unit_number,
        ];
    }

    private function composeVariables(
        array $attributes,
        ?ClientEntity $client,
        ?ClientCondominium $condominium,
        ?ClientUnit $unit,
        ?User $responsible,
        ?Contract $contract = null
    ): array {
        $proposal = !empty($attributes['proposal_id']) ? Proposal::query()->find((int) $attributes['proposal_id']) : null;
        $syndic = $condominium?->syndic;
        $startDate = $this->formatDate($attributes['start_date'] ?? null);
        $endDate = !empty($attributes['indefinite_term'])
            ? 'Prazo indeterminado'
            : $this->formatDate($attributes['end_date'] ?? null);
        $value = $this->moneyFromInput($attributes['contract_value'] ?? $contract?->contract_value);
        $city = trim((string) ($condominium?->address_json['city'] ?? ContractSettings::get('default_city', 'Vitória')));

        return [
            'contrato_codigo' => (string) ($attributes['code'] ?? $contract?->code ?? ''),
            'contrato_titulo' => (string) ($attributes['title'] ?? $contract?->title ?? ''),
            'cliente_nome' => (string) ($client?->display_name ?: $proposal?->client_name ?: ''),
            'cliente_documento' => (string) ($client?->cpf_cnpj ?: ''),
            'cliente_endereco' => $this->formatEntityAddress($client),
            'condominio_nome' => (string) ($condominium?->name ?: ''),
            'condominio_cnpj' => (string) ($condominium?->cnpj ?: ''),
            'condominio_endereco' => $this->formatCondominiumAddress($condominium),
            'sindico_nome' => (string) ($syndic?->display_name ?: ''),
            'sindico_cpf' => (string) ($syndic?->cpf_cnpj ?: ''),
            'unidade_numero' => (string) ($unit?->unit_number ?: ''),
            'bloco_nome' => (string) ($unit?->block?->name ?: ''),
            'contrato_valor' => $value !== null ? $this->formatMoney($value) : '',
            'contrato_valor_extenso' => $value !== null ? $this->moneyToWords($value) : '',
            'contrato_data_inicio' => $startDate,
            'contrato_data_fim' => $endDate,
            'contrato_dia_vencimento' => (string) ($attributes['due_day'] ?? $contract?->due_day ?? ''),
            'contrato_reajuste_indice' => (string) ($attributes['adjustment_index'] ?? $contract?->adjustment_index ?? ''),
            'data_atual' => Carbon::now()->locale('pt_BR')->translatedFormat('d \\d\\e F \\d\\e Y'),
            'cidade' => $city,
            'responsavel_nome' => (string) ($responsible?->name ?: ''),
        ];
    }

    private function formatEntityAddress(?ClientEntity $entity): string
    {
        $address = $entity?->primary_address_json ?? [];
        return $this->formatAddressParts($address);
    }

    private function formatCondominiumAddress(?ClientCondominium $condominium): string
    {
        $address = $condominium?->address_json ?? [];
        return $this->formatAddressParts($address);
    }

    private function formatAddressParts(array $address): string
    {
        return collect([
            $address['street'] ?? null,
            $address['number'] ?? null,
            $address['complement'] ?? null,
            $address['neighborhood'] ?? null,
            $address['city'] ?? null,
            $address['state'] ?? null,
            $address['zip'] ?? null,
        ])->filter(fn ($value) => trim((string) $value) !== '')->implode(', ');
    }

    private function formatDate(mixed $value): string
    {
        if (!$value) {
            return '';
        }

        try {
            return Carbon::parse($value)->format('d/m/Y');
        } catch (\Throwable) {
            return '';
        }
    }

    public function moneyFromInput(mixed $value): ?float
    {
        $raw = preg_replace('/[^\d,.-]/', '', (string) ($value ?? '')) ?: '';
        if ($raw === '') {
            return null;
        }

        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
        }

        $raw = str_replace(',', '.', $raw);

        return is_numeric($raw) ? round(max(0, (float) $raw), 2) : null;
    }

    public function formatMoney(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    public function moneyToWords(float $value): string
    {
        $integer = (int) floor($value);
        $cents = (int) round(($value - $integer) * 100);

        $intWords = $this->numberToWords($integer);
        $centWords = $cents > 0 ? $this->numberToWords($cents) : '';

        $result = $integer === 1 ? "{$intWords} real" : "{$intWords} reais";
        if ($cents > 0) {
            $result .= $cents === 1 ? " e {$centWords} centavo" : " e {$centWords} centavos";
        }

        return ucfirst($result) . '.';
    }

    private function numberToWords(int $number): string
    {
        $units = ['', 'um', 'dois', 'três', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'];
        $teens = [10 => 'dez', 11 => 'onze', 12 => 'doze', 13 => 'treze', 14 => 'quatorze', 15 => 'quinze', 16 => 'dezesseis', 17 => 'dezessete', 18 => 'dezoito', 19 => 'dezenove'];
        $tens = ['', '', 'vinte', 'trinta', 'quarenta', 'cinquenta', 'sessenta', 'setenta', 'oitenta', 'noventa'];
        $hundreds = ['', 'cento', 'duzentos', 'trezentos', 'quatrocentos', 'quinhentos', 'seiscentos', 'setecentos', 'oitocentos', 'novecentos'];

        if ($number === 0) {
            return 'zero';
        }

        if ($number === 100) {
            return 'cem';
        }

        $parts = [];
        $millions = intdiv($number, 1000000);
        $number %= 1000000;
        $thousands = intdiv($number, 1000);
        $number %= 1000;

        if ($millions > 0) {
            $parts[] = $millions === 1 ? 'um milhão' : $this->numberToWords($millions) . ' milhões';
        }

        if ($thousands > 0) {
            $parts[] = $thousands === 1 ? 'mil' : $this->numberToWords($thousands) . ' mil';
        }

        if ($number > 0) {
            $parts[] = $this->numberBelowThousand($number, $units, $teens, $tens, $hundreds);
        }

        return $this->joinWords($parts);
    }

    private function numberBelowThousand(int $number, array $units, array $teens, array $tens, array $hundreds): string
    {
        if ($number === 100) {
            return 'cem';
        }

        $parts = [];
        $hundred = intdiv($number, 100);
        $rest = $number % 100;

        if ($hundred > 0) {
            $parts[] = $hundreds[$hundred];
        }

        if ($rest >= 10 && $rest <= 19) {
            $parts[] = $teens[$rest];
        } else {
            $ten = intdiv($rest, 10);
            $unit = $rest % 10;
            if ($ten > 0) {
                $parts[] = $tens[$ten];
            }
            if ($unit > 0) {
                $parts[] = $units[$unit];
            }
        }

        return $this->joinWords($parts);
    }

    private function joinWords(array $parts): string
    {
        $parts = array_values(array_filter($parts, fn ($part) => trim((string) $part) !== ''));
        $count = count($parts);

        if ($count <= 1) {
            return $parts[0] ?? '';
        }

        if ($count === 2) {
            return $parts[0] . ' e ' . $parts[1];
        }

        $last = array_pop($parts);
        return implode(', ', $parts) . ' e ' . $last;
    }
}
