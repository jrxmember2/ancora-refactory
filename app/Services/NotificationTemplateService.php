<?php

namespace App\Services;

use App\Models\ClientEntity;
use App\Models\CobrancaCase;
use App\Models\ProcessCase;
use App\Models\ProcessCasePhase;

class NotificationTemplateService
{
    public static function variableDefinitions(): array
    {
        return [
            ['key' => 'cliente_nome', 'label' => 'Nome do destinatario', 'modules' => 'Processos e cobranca'],
            ['key' => 'sindico_nome', 'label' => 'Nome do sindico', 'modules' => 'Processos e cobranca'],
            ['key' => 'condominio_nome', 'label' => 'Nome do condominio', 'modules' => 'Processos e cobranca'],
            ['key' => 'unidade_numero', 'label' => 'Numero da unidade', 'modules' => 'Cobranca'],
            ['key' => 'bloco_nome', 'label' => 'Nome do bloco', 'modules' => 'Cobranca'],
            ['key' => 'vencimento', 'label' => 'Datas de vencimento', 'modules' => 'Cobranca'],
            ['key' => 'cotas_vencidas', 'label' => 'Lista completa das cotas vencidas', 'modules' => 'Cobranca e e-mail'],
            ['key' => 'devedor_nome', 'label' => 'Nome do devedor/proprietario', 'modules' => 'Cobranca'],
            ['key' => 'processo_numero', 'label' => 'Numero do processo', 'modules' => 'Processos'],
            ['key' => 'ultimo_andamento', 'label' => 'Descricao do ultimo andamento', 'modules' => 'Processos'],
            ['key' => 'andamento_data', 'label' => 'Data do andamento', 'modules' => 'Processos'],
            ['key' => 'os_numero', 'label' => 'Numero da OS', 'modules' => 'Cobranca'],
        ];
    }

    public static function defaultProcessWhatsappTemplate(): string
    {
        return implode("\n", [
            'Ola, {{cliente_nome}}.',
            'Foi registrado um novo andamento no processo {{processo_numero}}.',
            '',
            'Condominio: {{condominio_nome}}',
            'Atualizacao: {{ultimo_andamento}}',
            'Data: {{andamento_data}}',
        ]);
    }

    public static function defaultCollectionWhatsappTemplate(): string
    {
        return implode("\n", [
            'Ola, {{cliente_nome}}.',
            'Identificamos cotas vencidas vinculadas ao condominio {{condominio_nome}}.',
            '',
            'Unidade: {{unidade_numero}}',
            'Bloco: {{bloco_nome}}',
            'Vencimento(s): {{vencimento}}',
            'OS: {{os_numero}}',
        ]);
    }

    public static function defaultCollectionEmailSubject(): string
    {
        return 'Inadimplencia - {{condominio_nome}} - Unidade {{unidade_numero}}';
    }

    public static function defaultCollectionEmailBody(): string
    {
        return implode("\n", [
            'Ola, {{cliente_nome}}.',
            '',
            'Constam cotas vencidas vinculadas ao condominio {{condominio_nome}}.',
            'Unidade: {{unidade_numero}}',
            'Bloco: {{bloco_nome}}',
            'OS: {{os_numero}}',
            '',
            'Cotas vencidas:',
            '{{cotas_vencidas}}',
        ]);
    }

    public function render(string $template, array $variables): string
    {
        $map = [];
        foreach ($variables as $key => $value) {
            $map['{{' . $key . '}}'] = (string) $value;
        }

        return strtr($template, $map);
    }

    public function processVariables(ProcessCase $case, ProcessCasePhase $phase, ?ClientEntity $recipient = null): array
    {
        $case->loadMissing([
            'client',
            'clientCondominium.syndic',
        ]);

        $recipientName = trim((string) ($recipient?->display_name ?: $case->client_name_snapshot ?: 'Cliente'));
        $when = $phase->phase_date?->format('d/m/Y') ?: '';
        if ($phase->phase_time) {
            $when .= ($when !== '' ? ' ' : '') . substr((string) $phase->phase_time, 0, 5);
        }

        return [
            'cliente_nome' => $recipientName,
            'sindico_nome' => (string) ($case->clientCondominium?->syndic?->display_name ?: ''),
            'condominio_nome' => (string) ($case->clientCondominium?->name ?: ''),
            'unidade_numero' => '',
            'bloco_nome' => '',
            'vencimento' => '',
            'cotas_vencidas' => '',
            'devedor_nome' => '',
            'processo_numero' => (string) ($case->process_number ?: ('Processo #' . $case->id)),
            'ultimo_andamento' => (string) $phase->description,
            'andamento_data' => $when,
            'os_numero' => '',
        ];
    }

    public function collectionVariables(CobrancaCase $case): array
    {
        $case->loadMissing([
            'condominium.syndic',
            'block',
            'unit',
            'debtor',
            'quotas',
        ]);

        $quotas = $case->quotas
            ->sortBy(fn ($item) => optional($item->due_date)->format('Y-m-d') ?: '')
            ->values();
        $overdueQuotas = $quotas
            ->filter(fn ($item) => $item->due_date && $item->due_date->copy()->startOfDay()->lte(now()->startOfDay()))
            ->values();
        $listedQuotas = $overdueQuotas->isNotEmpty() ? $overdueQuotas : $quotas;

        $debtorName = (string) ($case->debtor_name_snapshot ?: $case->debtor?->display_name ?: 'Cliente');
        $dueDates = $listedQuotas
            ->map(fn ($item) => optional($item->due_date)->format('d/m/Y'))
            ->filter()
            ->unique()
            ->implode(', ');

        $quotaLines = $listedQuotas
            ->map(function ($item) {
                $label = trim((string) ($item->reference_label ?: ('Cota ' . ($item->id ?? ''))));
                $dueDate = optional($item->due_date)->format('d/m/Y') ?: 'Sem data';
                $amount = number_format((float) ($item->updated_amount ?? $item->original_amount ?? 0), 2, ',', '.');

                return '- ' . $label . ' - venc. ' . $dueDate . ' - R$ ' . $amount;
            })
            ->implode("\n");

        return [
            'cliente_nome' => $debtorName,
            'sindico_nome' => (string) ($case->condominium?->syndic?->display_name ?: ''),
            'condominio_nome' => (string) ($case->condominium?->name ?: ''),
            'unidade_numero' => (string) ($case->unit?->unit_label ?: $case->unit?->unit_number ?: ''),
            'bloco_nome' => (string) ($case->block?->name ?: ''),
            'vencimento' => $dueDates,
            'cotas_vencidas' => $quotaLines !== '' ? $quotaLines : '- Nenhuma cota vencida localizada.',
            'devedor_nome' => $debtorName,
            'processo_numero' => '',
            'ultimo_andamento' => '',
            'andamento_data' => '',
            'os_numero' => (string) ($case->os_number ?: ''),
        ];
    }

    public function collectionEmailHtml(string $subject, string $bodyText): string
    {
        return view('emails.cobrancas.collection-notice', [
            'subject' => trim($subject),
            'bodyText' => trim($bodyText),
        ])->render();
    }
}
