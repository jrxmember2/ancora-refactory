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
    private const DEFAULT_CONTRACTED_PARTY = [
        'name' => 'REBECA DA SILVA PAULA SOCIEDADE INDIVIDUAL DE ADVOCACIA',
        'document' => '52.816.983/0001-32',
        'responsible' => 'REBECA DA SILVA PAULA',
        'responsible_document' => 'OAB/ES sob o nº. 25.057',
    ];

    public function draftVariables(array $attributes): array
    {
        $client = !empty($attributes['client_id']) ? ClientEntity::query()->find((int) $attributes['client_id']) : null;
        $condominium = !empty($attributes['condominium_id']) ? ClientCondominium::query()->find((int) $attributes['condominium_id']) : null;
        $unit = !empty($attributes['unit_id']) ? ClientUnit::query()->with(['block', 'condominium', 'owner'])->find((int) $attributes['unit_id']) : null;
        $syndic = !empty($attributes['syndico_entity_id']) ? ClientEntity::query()->find((int) $attributes['syndico_entity_id']) : null;
        $responsible = !empty($attributes['responsible_user_id']) ? User::query()->find((int) $attributes['responsible_user_id']) : null;

        if (!$condominium && $unit?->condominium) {
            $condominium = $unit->condominium;
        }

        if (!$syndic && $condominium?->syndic) {
            $syndic = $condominium->syndic;
        }

        if (!$client && $unit?->owner) {
            $client = $unit->owner;
        }

        return $this->composeVariables($attributes, $client, $condominium, $unit, $responsible, null, $syndic);
    }

    public function contractVariables(Contract $contract): array
    {
        $contract->loadMissing([
            'client',
            'condominium.syndic',
            'syndic',
            'unit.block',
            'unit.owner',
            'responsible',
        ]);

        return $this->composeVariables(
            $contract->toArray(),
            $contract->client,
            $contract->condominium,
            $contract->unit,
            $contract->responsible,
            $contract,
            $contract->syndic ?: $contract->condominium?->syndic
        );
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

        $html = strtr($html, $map);

        return strtr($html, [
            '{{numero_pagina}}' => '<span class="ancora-page-number"></span>',
            '{{total_paginas}}' => '<span class="ancora-page-total"></span>',
        ]);
    }

    public function documentPayload(Contract $contract, ?string $contentHtml = null): array
    {
        $variables = $this->contractVariables($contract);
        $contract->loadMissing(['category', 'template', 'client', 'condominium.syndic', 'syndic', 'unit.block', 'responsible', 'creator']);
        $brand = AncoraSettings::brand();
        $parties = $this->documentParties($contract, $variables, $brand);

        $city = trim((string) ($variables['cidade'] ?? ''));
        $state = trim((string) ContractSettings::get('default_state', 'ES'));
        $signatureText = trim((string) ContractSettings::get('signature_text', '________________________________________'));
        $dateLong = Carbon::now()->locale('pt_BR')->translatedFormat('d \\d\\e F \\d\\e Y');

        return [
            'brand' => $brand,
            'settings' => [
                'city' => $city,
                'state' => $state,
                'footer_text' => 'documento gerado pelo ancora hub',
                'show_logo' => ContractSettings::bool('show_logo', true),
                'signature_text' => $signatureText,
            ],
            'contract' => $contract,
            'variables' => $variables,
            'rendered_title' => $this->documentTitle($contract),
            'rendered_header_html' => $this->renderDocumentFragment((string) ($contract->template?->header_html ?? ''), $variables),
            'rendered_qualification_html' => $this->renderDocumentFragment((string) ($contract->template?->qualification_html ?? ''), $variables),
            'rendered_footer_html' => $this->renderDocumentFragment((string) ($contract->template?->footer_html ?? ''), $variables),
            'content_html' => $contentHtml !== null && trim($contentHtml) !== ''
                ? $this->renderHtml($contentHtml, $variables)
                : $this->renderHtml((string) $contract->content_html, $variables),
            'date_long' => $dateLong,
            'location_label' => trim($city . ($state !== '' ? '/' . strtolower($state) : '')),
            'client_label' => $contract->client?->display_name ?: ($contract->condominium?->name ?: 'Nao informado'),
            'condominium_label' => $contract->condominium?->name,
            'unit_label' => $contract->unit?->unit_number,
            'parties' => $parties,
            'meta_blocks' => $this->documentMetaBlocks($contract, $variables),
        ];
    }

    private function composeVariables(
        array $attributes,
        ?ClientEntity $client,
        ?ClientCondominium $condominium,
        ?ClientUnit $unit,
        ?User $responsible,
        ?Contract $contract = null,
        ?ClientEntity $syndic = null
    ): array {
        $proposal = !empty($attributes['proposal_id']) ? Proposal::query()->find((int) $attributes['proposal_id']) : null;
        $startDate = $this->formatDate($attributes['start_date'] ?? null);
        $endDate = !empty($attributes['indefinite_term'])
            ? 'Prazo indeterminado'
            : $this->formatDate($attributes['end_date'] ?? null);
        $value = $this->moneyFromInput($attributes['contract_value'] ?? $contract?->contract_value);
        $city = trim((string) ($condominium?->address_json['city'] ?? ContractSettings::get('default_city', 'Vitoria')));
        $syndicVariables = $this->syndicVariables($syndic);

        return array_merge([
            'contrato_codigo' => (string) ($attributes['code'] ?? $contract?->code ?? ''),
            'contrato_titulo' => (string) ($attributes['title'] ?? $contract?->title ?? ''),
            'cliente_nome' => (string) ($client?->display_name ?: $proposal?->client_name ?: ''),
            'cliente_documento' => (string) ($client?->cpf_cnpj ?: ''),
            'cliente_endereco' => $this->formatEntityAddress($client),
            'condominio_nome' => (string) ($condominium?->name ?: ''),
            'condominio_cnpj' => (string) ($condominium?->cnpj ?: ''),
            'condominio_endereco' => $this->formatCondominiumAddress($condominium),
            'sindico_nome' => $syndicVariables['name'],
            'sindico_nome_simples' => $syndicVariables['simple_name'],
            'sindico_tipo_pessoa' => $syndicVariables['person_type'],
            'sindico_cpf' => $syndicVariables['cpf'],
            'sindico_cnpj' => $syndicVariables['cnpj'],
            'sindico_documento' => $syndicVariables['document'],
            'sindico_endereco' => $this->formatEntityAddress($syndic),
            'sindico_email' => $syndicVariables['email'],
            'sindico_telefone' => $syndicVariables['phone'],
            'sindico_empresa_nome' => $syndicVariables['company_name'],
            'sindico_empresa_cnpj' => $syndicVariables['cnpj'],
            'sindico_qualificacao' => $syndicVariables['qualification'],
            'sindico_representante_nome' => $syndicVariables['representative_name'],
            'sindico_representante_documento' => $syndicVariables['representative_document'],
            'sindico_representante_cpf' => $syndicVariables['representative_cpf'],
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
        ], $this->addressVariables('cliente', $client?->primary_address_json ?? []), $this->addressVariables('condominio', $condominium?->address_json ?? []), $this->addressVariables('sindico', $syndic?->primary_address_json ?? []), $this->signaturePresetVariables());
    }

    private function syndicVariables(?ClientEntity $syndic): array
    {
        if (!$syndic) {
            return [
                'name' => '',
                'simple_name' => '',
                'person_type' => '',
                'document' => '',
                'cpf' => '',
                'cnpj' => '',
                'email' => '',
                'phone' => '',
                'company_name' => '',
                'qualification' => '',
                'representative_name' => '',
                'representative_document' => '',
                'representative_cpf' => '',
            ];
        }

        $simpleName = trim((string) ($syndic->display_name ?: $syndic->legal_name ?: ''));
        $document = trim((string) ($syndic->cpf_cnpj ?: ''));
        $entityType = trim((string) ($syndic->entity_type ?? 'pf'));
        $email = trim((string) collect($syndic->emails_json ?? [])->pluck('email')->filter()->first());
        $phone = trim((string) collect($syndic->phones_json ?? [])->pluck('number')->filter()->first());

        if ($entityType !== 'pj') {
            $qualification = trim($simpleName . ($document !== '' ? ', inscrito(a) no CPF sob o no ' . $document : ''));

            return [
                'name' => $simpleName,
                'simple_name' => $simpleName,
                'person_type' => 'PF',
                'document' => $document,
                'cpf' => $document,
                'cnpj' => '',
                'email' => $email,
                'phone' => $phone,
                'company_name' => '',
                'qualification' => $qualification,
                'representative_name' => '',
                'representative_document' => '',
                'representative_cpf' => '',
            ];
        }

        $representative = $this->preferredShareholder($syndic);
        $companyName = trim((string) ($syndic->legal_name ?: $simpleName));
        $qualification = $companyName;

        if ($document !== '') {
            $qualification .= ', inscrita no CNPJ sob o no ' . $document;
        }

        $representativeName = trim((string) ($representative['name'] ?? ''));
        $representativeDocument = trim((string) ($representative['document'] ?? ''));

        if ($representativeName !== '') {
            $qualification .= ', neste ato representada por ' . $representativeName;
            if ($representativeDocument !== '') {
                $qualification .= ', inscrito(a) no CPF sob o no ' . $representativeDocument;
            }
        }

        return [
            'name' => $qualification,
            'simple_name' => $simpleName !== '' ? $simpleName : $companyName,
            'person_type' => 'PJ',
            'document' => $document,
            'cpf' => '',
            'cnpj' => $document,
            'email' => $email,
            'phone' => $phone,
            'company_name' => $companyName,
            'qualification' => $qualification,
            'representative_name' => $representativeName,
            'representative_document' => $representativeDocument,
            'representative_cpf' => $representativeDocument,
        ];
    }

    private function preferredShareholder(?ClientEntity $entity): array
    {
        $rows = collect($entity?->shareholders_json ?? [])
            ->map(fn ($row) => [
                'name' => trim((string) ($row['name'] ?? '')),
                'document' => trim((string) ($row['document'] ?? '')),
                'role' => trim((string) ($row['role'] ?? '')),
            ])
            ->filter(fn ($row) => $row['name'] !== '' || $row['document'] !== '')
            ->values();

        if ($rows->isEmpty()) {
            return [
                'name' => trim((string) ($entity?->legal_representative ?? '')),
                'document' => '',
                'role' => 'Representante legal',
            ];
        }

        $preferred = $rows->first(function (array $row) {
            $role = mb_strtolower((string) ($row['role'] ?? ''), 'UTF-8');

            return str_contains($role, 'administrador')
                || str_contains($role, 'representante')
                || str_contains($role, 'socio');
        });

        return $preferred ?: $rows->first();
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

    private function addressVariables(string $prefix, array $address): array
    {
        return [
            $prefix . '_logradouro' => (string) ($address['street'] ?? ''),
            $prefix . '_numero' => (string) ($address['number'] ?? ''),
            $prefix . '_complemento' => (string) ($address['complement'] ?? ''),
            $prefix . '_bairro' => (string) ($address['neighborhood'] ?? ''),
            $prefix . '_cidade' => (string) ($address['city'] ?? ''),
            $prefix . '_estado' => (string) ($address['state'] ?? ''),
            $prefix . '_cep' => (string) ($address['zip'] ?? ''),
        ];
    }

    private function documentMetaBlocks(Contract $contract, array $variables): array
    {
        $blocks = [];

        $clientQualification = $this->qualificationLine(
            (string) ($variables['cliente_nome'] ?? ''),
            (string) ($variables['cliente_documento'] ?? ''),
            (string) ($variables['cliente_endereco'] ?? ''),
            'CPF/CNPJ'
        );
        if ($clientQualification !== '') {
            $blocks[] = ['label' => 'Cliente', 'value' => $clientQualification];
        }

        $condominiumQualification = $this->qualificationLine(
            (string) ($variables['condominio_nome'] ?? ''),
            (string) ($variables['condominio_cnpj'] ?? ''),
            (string) ($variables['condominio_endereco'] ?? ''),
            'CNPJ'
        );
        if ($condominiumQualification !== '') {
            $blocks[] = ['label' => 'Condominio', 'value' => $condominiumQualification];
        }

        $syndicQualification = trim((string) ($variables['sindico_qualificacao'] ?? $variables['sindico_nome'] ?? ''));
        if ($syndicQualification !== '') {
            $blocks[] = ['label' => 'Sindico(a)', 'value' => $syndicQualification];
        }

        $unit = trim((string) ($variables['unidade_numero'] ?? ''));
        $block = trim((string) ($variables['bloco_nome'] ?? ''));
        if ($unit !== '') {
            $blocks[] = ['label' => 'Unidade', 'value' => $block !== '' ? $block . ' - unidade ' . $unit : 'Unidade ' . $unit];
        }

        $value = trim((string) ($variables['contrato_valor'] ?? ''));
        if ($value !== '') {
            $blocks[] = ['label' => 'Valor', 'value' => $value];
        }

        if (!$contract->indefinite_term) {
            $endDate = trim((string) ($variables['contrato_data_fim'] ?? ''));
            if ($endDate !== '') {
                $blocks[] = ['label' => 'Vigencia final', 'value' => $endDate];
            }
        }

        return $blocks;
    }

    private function qualificationLine(string $name, string $document, string $address, string $documentLabel): string
    {
        $parts = array_filter([
            trim($name),
            trim($document) !== '' ? $documentLabel . ' ' . trim($document) : '',
            trim($address),
        ]);

        return implode(' - ', $parts);
    }

    private function renderDocumentFragment(string $html, array $variables): string
    {
        $html = trim($html);

        return $html === '' ? '' : $this->renderHtml($html, $variables);
    }

    private function documentTitle(Contract $contract): string
    {
        $title = trim((string) $contract->title);
        $type = trim((string) $contract->type);

        if ($title === '') {
            return '';
        }

        return mb_strtolower($title, 'UTF-8') === mb_strtolower($type, 'UTF-8') ? '' : $title;
    }

    private function documentParties(Contract $contract, array $variables, array $brand): array
    {
        return [
            'contracting' => $this->contractingParty($contract, $variables),
            'contracted' => $this->contractedParty($brand),
        ];
    }

    private function contractingParty(Contract $contract, array $variables): array
    {
        $syndic = $contract->syndic ?: $contract->condominium?->syndic;
        if ($syndic instanceof ClientEntity) {
            $isCompany = mb_strtoupper((string) ($variables['sindico_tipo_pessoa'] ?? ''), 'UTF-8') === 'PJ';

            return $this->partyCard('Contratante', array_values(array_filter([
                    ['label' => 'Nome', 'value' => trim((string) ($isCompany ? ($variables['sindico_empresa_nome'] ?? $variables['sindico_nome_simples'] ?? '') : ($variables['sindico_nome_simples'] ?? ''))), 'wide' => false],
                    ['label' => $isCompany ? 'CNPJ' : 'CPF', 'value' => trim((string) ($isCompany ? ($variables['sindico_empresa_cnpj'] ?? $variables['sindico_cnpj'] ?? '') : ($variables['sindico_cpf'] ?? $variables['sindico_documento'] ?? ''))), 'wide' => false],
                    ['label' => 'Responsavel', 'value' => trim((string) ($isCompany ? ($variables['sindico_representante_nome'] ?? '') : ($variables['sindico_nome_simples'] ?? ''))), 'wide' => false],
                    ['label' => $isCompany ? 'CPF' : 'Documento', 'value' => trim((string) ($isCompany ? ($variables['sindico_representante_cpf'] ?? '') : ($variables['sindico_documento'] ?? ''))), 'wide' => false],
                    ['label' => 'Endereco', 'value' => trim((string) ($variables['sindico_endereco'] ?? '')), 'wide' => true],
                    ['label' => 'E-mail', 'value' => trim((string) ($variables['sindico_email'] ?? '')), 'wide' => false],
                    ['label' => 'Telefone', 'value' => trim((string) ($variables['sindico_telefone'] ?? '')), 'wide' => false],
                ], fn (array $row) => trim((string) ($row['value'] ?? '')) !== '')));
        }

        if ($contract->client instanceof ClientEntity) {
            return $this->partyCard('Contratante', array_values(array_filter([
                    ['label' => 'Nome', 'value' => trim((string) ($variables['cliente_nome'] ?? '')), 'wide' => true],
                    ['label' => 'Documento', 'value' => trim((string) ($variables['cliente_documento'] ?? '')), 'wide' => false],
                    ['label' => 'Endereco', 'value' => trim((string) ($variables['cliente_endereco'] ?? '')), 'wide' => true],
                    ['label' => 'E-mail', 'value' => $this->entityEmail($contract->client), 'wide' => false],
                    ['label' => 'Telefone', 'value' => $this->entityPhone($contract->client), 'wide' => false],
                ], fn (array $row) => trim((string) ($row['value'] ?? '')) !== '')));
        }

        return $this->partyCard('Contratante', array_values(array_filter([
                ['label' => 'Nome', 'value' => trim((string) ($variables['condominio_nome'] ?? '')), 'wide' => true],
                ['label' => 'CNPJ', 'value' => trim((string) ($variables['condominio_cnpj'] ?? '')), 'wide' => false],
                ['label' => 'Responsavel', 'value' => trim((string) ($variables['sindico_nome_simples'] ?? '')), 'wide' => false],
                ['label' => 'CPF', 'value' => trim((string) ($variables['sindico_cpf'] ?? $variables['sindico_documento'] ?? '')), 'wide' => false],
                ['label' => 'Endereco', 'value' => trim((string) ($variables['condominio_endereco'] ?? '')), 'wide' => true],
                ['label' => 'E-mail', 'value' => $this->entityEmail($contract->syndic ?: $contract->condominium?->syndic), 'wide' => false],
                ['label' => 'Telefone', 'value' => $this->entityPhone($contract->syndic ?: $contract->condominium?->syndic), 'wide' => false],
            ], fn (array $row) => trim((string) ($row['value'] ?? '')) !== '')));
    }

    private function contractedParty(array $brand): array
    {
        $name = trim((string) ($brand['company_name'] ?? self::DEFAULT_CONTRACTED_PARTY['name'])) ?: self::DEFAULT_CONTRACTED_PARTY['name'];

        return $this->partyCard('Contratada', array_values(array_filter([
                ['label' => 'Nome', 'value' => $name, 'wide' => true],
                ['label' => 'CNPJ', 'value' => self::DEFAULT_CONTRACTED_PARTY['document'], 'wide' => true],
                ['label' => 'Responsavel', 'value' => self::DEFAULT_CONTRACTED_PARTY['responsible'] . ', inscrita na ' . self::DEFAULT_CONTRACTED_PARTY['responsible_document'], 'wide' => true],
                ['label' => 'Endereco', 'value' => trim((string) ($brand['company_address'] ?? '')), 'wide' => true],
                ['label' => 'E-mail', 'value' => trim((string) ($brand['company_email'] ?? '')), 'wide' => false],
                ['label' => 'Telefone', 'value' => trim((string) ($brand['company_phone'] ?? '')), 'wide' => false],
            ], fn (array $row) => trim((string) ($row['value'] ?? '')) !== '')));
    }

    private function partyCard(string $title, array $rows): array
    {
        return [
            'title' => $title,
            'rows' => $rows,
            'grid_rows' => $this->groupPartyRows($rows),
        ];
    }

    private function groupPartyRows(array $rows): array
    {
        $grouped = [];
        $count = count($rows);

        for ($index = 0; $index < $count; $index++) {
            $row = $rows[$index] ?? [];

            if (($row['wide'] ?? false) === true) {
                $grouped[] = [
                    'wide' => true,
                    'columns' => [$row],
                ];
                continue;
            }

            $next = $rows[$index + 1] ?? null;
            if (is_array($next) && (($next['wide'] ?? false) === false)) {
                $grouped[] = [
                    'wide' => false,
                    'columns' => [$row, $next],
                ];
                $index++;
                continue;
            }

            $grouped[] = [
                'wide' => false,
                'columns' => [$row],
            ];
        }

        return $grouped;
    }

    private function entityEmail(?ClientEntity $entity): string
    {
        return trim((string) collect($entity?->emails_json ?? [])->pluck('email')->filter()->first());
    }

    private function entityPhone(?ClientEntity $entity): string
    {
        return trim((string) collect($entity?->phones_json ?? [])->pluck('number')->filter()->first());
    }

    private function signaturePresetVariables(): array
    {
        $variables = [];

        $rows = collect(ContractSettings::jsonArray('assinafy_default_signers_json'))
            ->map(fn ($row) => [
                'name' => trim((string) ($row['name'] ?? '')),
                'email' => trim((string) ($row['email'] ?? '')),
                'phone' => trim((string) ($row['phone'] ?? '')),
                'document' => trim((string) ($row['document_number'] ?? '')),
                'role' => mb_strtolower(trim((string) ($row['role_label'] ?? '')), 'UTF-8'),
            ]);

        foreach (['signatario', 'testemunha'] as $role) {
            $rows
                ->filter(fn ($row) => $row['role'] === $role)
                ->values()
                ->each(function (array $row, int $index) use (&$variables, $role) {
                    $base = $role . '_' . ($index + 1);
                    $variables[$base . '_nome'] = $row['name'];
                    $variables[$base . '_email'] = $row['email'];
                    $variables[$base . '_telefone'] = $row['phone'];
                    $variables[$base . '_documento'] = $row['document'];
                });
        }

        return $variables;
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
        $units = ['', 'um', 'dois', 'tres', 'quatro', 'cinco', 'seis', 'sete', 'oito', 'nove'];
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
            $parts[] = $millions === 1 ? 'um milhao' : $this->numberToWords($millions) . ' milhoes';
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
