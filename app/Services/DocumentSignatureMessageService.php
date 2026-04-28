<?php

namespace App\Services;

use App\Models\Contract;
use App\Models\CobrancaCase;
use App\Support\ContractSettings;
use App\Support\Signatures\DocumentSignatureCatalog;
use Carbon\Carbon;

class DocumentSignatureMessageService
{
    public function __construct(
        private readonly ContractRenderService $contractRenderService,
    ) {
    }

    public function definitions(): array
    {
        return DocumentSignatureCatalog::messageVariableDefinitions();
    }

    public function renderForContract(string $message, Contract $contract): string
    {
        $contract->loadMissing([
            'client',
            'condominium.syndic',
            'syndic',
            'unit.block',
        ]);

        $variables = $this->contractRenderService->contractVariables($contract);
        $variables['sindico_nome'] = (string) ($variables['sindico_nome_simples'] ?? $variables['sindico_nome'] ?? '');

        $variables = array_merge(
            $variables,
            [
                'documento_titulo' => (string) ($contract->title ?: $contract->code ?: 'Contrato'),
                'tipo_documento' => (string) ($contract->type ?: 'Contrato'),
                'condominio_cidade' => (string) ($contract->condominium?->address_json['city'] ?? ''),
                'devedor_nome' => '',
                'devedor_documento' => '',
                'os_numero' => '',
            ]
        );

        return $this->render($message, $variables);
    }

    public function renderForCobranca(string $message, CobrancaCase $case): string
    {
        $case->loadMissing([
            'condominium.syndic',
            'block',
            'unit',
            'debtor',
        ]);

        $variables = [
            'documento_titulo' => 'Termo de acordo - ' . (string) ($case->os_number ?: 'OS'),
            'tipo_documento' => 'Termo de acordo',
            'cliente_nome' => (string) ($case->debtor_name_snapshot ?: $case->debtor?->display_name ?: ''),
            'condominio_nome' => (string) ($case->condominium?->name ?: ''),
            'condominio_cidade' => (string) ($case->condominium?->address_json['city'] ?? ''),
            'unidade_numero' => (string) ($case->unit?->unit_number ?: $case->unit?->unit_label ?: ''),
            'bloco_nome' => (string) ($case->block?->name ?: ''),
            'sindico_nome' => (string) ($case->condominium?->syndic?->display_name ?: ''),
            'devedor_nome' => (string) ($case->debtor_name_snapshot ?: $case->debtor?->display_name ?: ''),
            'devedor_documento' => (string) ($case->debtor_document_snapshot ?: $case->debtor?->cpf_cnpj ?: ''),
            'os_numero' => (string) ($case->os_number ?: ''),
            'contrato_codigo' => '',
            'cidade' => (string) ($case->condominium?->address_json['city'] ?? ContractSettings::get('default_city', 'Vitoria')),
            'data_atual' => Carbon::now()->locale('pt_BR')->translatedFormat('d \\d\\e F \\d\\e Y'),
        ];

        return $this->render($message, $variables);
    }

    public function render(string $message, array $variables): string
    {
        $map = [];
        foreach ($variables as $key => $value) {
            $map['{{' . $key . '}}'] = (string) $value;
        }

        return strtr($message, $map);
    }
}
