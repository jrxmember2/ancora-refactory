<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\CobrancaCase;
use Carbon\Carbon;

class DocumentSignatureMessageService
{
    public function __construct(
        private readonly ContractRenderService $contractRenderService,
    ) {
    }

    public function renderForContract(Contract $contract, ?string $message): ?string
    {
        return $this->render($message, $this->contractVariables($contract));
    }

    public function renderForCobranca(CobrancaCase $case, ?string $message): ?string
    {
        return $this->render($message, $this->cobrancaVariables($case));
    }

    public function availableVariables(?string $mode = null): array
    {
        $rows = [
            ['token' => '{{documento_titulo}}', 'label' => 'Titulo do documento', 'modes' => ['contract', 'cobranca']],
            ['token' => '{{tipo_documento}}', 'label' => 'Tipo do documento', 'modes' => ['contract', 'cobranca']],
            ['token' => '{{data_atual}}', 'label' => 'Data atual por extenso', 'modes' => ['contract', 'cobranca']],
            ['token' => '{{cliente_nome}}', 'label' => 'Cliente vinculado', 'modes' => ['contract']],
            ['token' => '{{condominio_nome}}', 'label' => 'Nome do condominio', 'modes' => ['contract', 'cobranca']],
            ['token' => '{{unidade_numero}}', 'label' => 'Numero da unidade', 'modes' => ['contract', 'cobranca']],
            ['token' => '{{bloco_nome}}', 'label' => 'Bloco da unidade', 'modes' => ['contract', 'cobranca']],
            ['token' => '{{sindico_nome}}', 'label' => 'Sindico ou representante configurado', 'modes' => ['contract']],
            ['token' => '{{contrato_codigo}}', 'label' => 'Codigo interno do contrato', 'modes' => ['contract']],
            ['token' => '{{contrato_titulo}}', 'label' => 'Titulo do contrato', 'modes' => ['contract']],
            ['token' => '{{contrato_valor}}', 'label' => 'Valor do contrato', 'modes' => ['contract']],
            ['token' => '{{devedor_nome}}', 'label' => 'Nome do devedor', 'modes' => ['cobranca']],
            ['token' => '{{devedor_documento}}', 'label' => 'CPF/CNPJ do devedor', 'modes' => ['cobranca']],
            ['token' => '{{os_numero}}', 'label' => 'Numero da OS', 'modes' => ['cobranca']],
            ['token' => '{{valor_acordo}}', 'label' => 'Valor total do acordo', 'modes' => ['cobranca']],
        ];

        if ($mode === null) {
            return $rows;
        }

        return array_values(array_filter($rows, static fn (array $row) => in_array($mode, $row['modes'], true)));
    }

    private function render(?string $message, array $variables): ?string
    {
        $message = trim((string) $message);
        if ($message === '') {
            return null;
        }

        $map = [];
        foreach ($variables as $key => $value) {
            $map['{{' . $key . '}}'] = trim((string) $value);
        }

        return strtr($message, $map);
    }

    private function contractVariables(Contract $contract): array
    {
        $contract->loadMissing([
            'template',
            'client',
            'condominium.syndic',
            'syndic',
            'unit.block',
            'responsible',
        ]);

        $variables = $this->contractRenderService->contractVariables($contract);
        $variables['documento_titulo'] = (string) ($contract->title ?: $contract->template?->default_contract_title ?: $contract->template?->name ?: 'Contrato');
        $variables['tipo_documento'] = (string) ($contract->template?->document_type ?: $contract->type ?: 'Contrato');
        $variables['contrato_status'] = (string) ($contract->status ?: '');

        return $variables;
    }

    private function cobrancaVariables(CobrancaCase $case): array
    {
        $case->loadMissing([
            'condominium',
            'block',
            'unit.block',
            'debtor',
            'creator',
            'updater',
        ]);

        $agreementValue = $case->agreement_total !== null
            ? $this->contractRenderService->formatMoney((float) $case->agreement_total)
            : '';

        return [
            'documento_titulo' => 'Termo de acordo ' . (string) ($case->os_number ?: ''),
            'tipo_documento' => 'Termo de acordo',
            'data_atual' => Carbon::now()->locale('pt_BR')->translatedFormat('d \\d\\e F \\d\\e Y'),
            'condominio_nome' => (string) ($case->condominium?->name ?: ''),
            'unidade_numero' => (string) ($case->unit?->unit_number ?: ''),
            'bloco_nome' => (string) ($case->block?->name ?: $case->unit?->block?->name ?: ''),
            'devedor_nome' => (string) ($case->debtor_name_snapshot ?: $case->debtor?->display_name ?: ''),
            'devedor_documento' => (string) ($case->debtor_document_snapshot ?: $case->debtor?->cpf_cnpj ?: ''),
            'os_numero' => (string) ($case->os_number ?: ''),
            'valor_acordo' => $agreementValue,
        ];
    }
}
