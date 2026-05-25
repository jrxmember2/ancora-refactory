<?php

namespace App\Support\Hub;

use App\Models\Contract;
use App\Models\ContractAttachment;
use App\Models\ContractVersion;
use App\Models\DocumentSignatureEvent;
use App\Models\DocumentSignatureRequest;
use App\Models\DocumentSignatureSigner;
use App\Models\FinancialPayable;
use App\Models\FinancialReceivable;
use App\Models\FinancialTransaction;
use App\Models\Proposal;
use App\Models\ProposalAttachment;
use App\Models\ProposalHistory;
use App\Services\DocumentSignatureService;
use App\Support\Contracts\ContractCatalog;
use App\Support\Financeiro\FinancialCatalog;
use Illuminate\Support\Str;

class HubOfficePresenter
{
    public static function proposalSummary(Proposal $proposal): array
    {
        return [
            'id' => (int) $proposal->id,
            'code' => (string) ($proposal->proposal_code ?: ('Proposta #' . $proposal->id)),
            'client_name' => (string) ($proposal->client_name ?: 'Cliente não informado'),
            'service_id' => $proposal->service_id ? (int) $proposal->service_id : null,
            'service_name' => $proposal->servico?->name,
            'status_id' => $proposal->response_status_id ? (int) $proposal->response_status_id : null,
            'status_label' => $proposal->statusRetorno?->name ?: 'Sem status',
            'status_color' => $proposal->statusRetorno?->color_hex,
            'proposal_total' => $proposal->proposal_total !== null ? (float) $proposal->proposal_total : null,
            'proposal_total_label' => self::money($proposal->proposal_total),
            'closed_total' => $proposal->closed_total !== null ? (float) $proposal->closed_total : null,
            'closed_total_label' => self::money($proposal->closed_total),
            'without_amount' => (bool) $proposal->without_amount,
            'proposal_date' => $proposal->proposal_date?->toDateString(),
            'proposal_date_br' => $proposal->proposal_date?->format('d/m/Y'),
            'followup_date' => $proposal->followup_date?->toDateString(),
            'followup_date_br' => $proposal->followup_date?->format('d/m/Y'),
            'requester_name' => $proposal->requester_name ? (string) $proposal->requester_name : null,
            'attachments_count' => (int) ($proposal->attachments_count ?? 0),
            'updated_at' => $proposal->updated_at?->toAtomString(),
            'updated_at_br' => $proposal->updated_at?->format('d/m/Y H:i'),
        ];
    }

    public static function proposalDetail(Proposal $proposal): array
    {
        return array_merge(self::proposalSummary($proposal), [
            'administradora_name' => $proposal->administradora?->name,
            'send_method_name' => $proposal->formaEnvio?->name,
            'requester_phone' => $proposal->requester_phone ? (string) $proposal->requester_phone : null,
            'contact_email' => $proposal->contact_email ? (string) $proposal->contact_email : null,
            'has_referral' => (bool) $proposal->has_referral,
            'referral_name' => $proposal->referral_name ? (string) $proposal->referral_name : null,
            'refusal_reason' => $proposal->refusal_reason ? (string) $proposal->refusal_reason : null,
            'validity_days' => $proposal->validity_days !== null ? (int) $proposal->validity_days : null,
            'notes' => $proposal->notes ? (string) $proposal->notes : null,
            'history' => $proposal->relationLoaded('history')
                ? $proposal->history->map(fn (ProposalHistory $item) => self::proposalHistory($item))->values()->all()
                : [],
            'attachments' => $proposal->relationLoaded('attachments')
                ? $proposal->attachments->map(fn (ProposalAttachment $item) => self::proposalAttachment($item))->values()->all()
                : [],
        ]);
    }

    public static function proposalHistory(ProposalHistory $history): array
    {
        return [
            'id' => (int) $history->id,
            'action' => (string) $history->action,
            'summary' => (string) ($history->summary ?: 'Atualização registrada.'),
            'user_email' => $history->user_email ? (string) $history->user_email : null,
            'created_at' => $history->created_at?->toAtomString(),
            'created_at_br' => $history->created_at?->format('d/m/Y H:i'),
        ];
    }

    public static function proposalAttachment(ProposalAttachment $attachment): array
    {
        return [
            'id' => (int) $attachment->id,
            'name' => (string) $attachment->original_name,
            'mime_type' => $attachment->mime_type ? (string) $attachment->mime_type : 'application/pdf',
            'file_size' => (int) ($attachment->file_size ?? 0),
            'file_size_label' => self::fileSize($attachment->file_size),
            'created_at' => $attachment->created_at?->toAtomString(),
            'created_at_br' => $attachment->created_at?->format('d/m/Y H:i'),
        ];
    }

    public static function contractStatusOptions(): array
    {
        return collect(ContractCatalog::statuses())
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    public static function contractSummary(Contract $contract): array
    {
        $isExpired = self::contractExpired($contract);
        $effectiveStatus = $isExpired && !in_array((string) $contract->status, ['rescindido', 'cancelado', 'arquivado'], true)
            ? 'vencido'
            : (string) $contract->status;

        return [
            'id' => (int) $contract->id,
            'code' => (string) ($contract->code ?: ('Contrato #' . $contract->id)),
            'title' => (string) ($contract->title ?: 'Contrato sem título'),
            'client_name' => $contract->client?->display_name ?: ($contract->condominium?->name ?: ($contract->syndic?->display_name ?: 'Não informado')),
            'object_label' => $contract->title ? (string) $contract->title : (string) ($contract->type ?: 'Contrato'),
            'status' => $effectiveStatus,
            'status_label' => ContractCatalog::statuses()[$effectiveStatus] ?? Str::headline($effectiveStatus),
            'type' => $contract->type ? (string) $contract->type : null,
            'category_name' => $contract->category?->name,
            'value' => self::contractValue($contract),
            'value_label' => self::money(self::contractValue($contract)),
            'payment_method' => $contract->payment_method ? (string) $contract->payment_method : null,
            'payment_method_label' => $contract->payment_method
                ? (FinancialCatalog::paymentMethods()[$contract->payment_method] ?? Str::headline((string) $contract->payment_method))
                : null,
            'start_date' => $contract->start_date?->toDateString(),
            'start_date_br' => $contract->start_date?->format('d/m/Y'),
            'end_date' => $contract->end_date?->toDateString(),
            'end_date_br' => $contract->end_date?->format('d/m/Y'),
            'is_expired' => $isExpired,
            'has_final_pdf' => trim((string) $contract->final_pdf_path) !== '',
            'signature_pending' => $contract->relationLoaded('signatureRequests')
                ? $contract->signatureRequests->contains(fn (DocumentSignatureRequest $item) => in_array($item->status, ['pending_signatures', 'partially_signed', 'metadata_ready', 'uploaded', 'certificating'], true))
                : false,
            'updated_at' => $contract->updated_at?->toAtomString(),
            'updated_at_br' => $contract->updated_at?->format('d/m/Y H:i'),
        ];
    }

    public static function contractDetail(Contract $contract): array
    {
        $latestSignature = $contract->relationLoaded('signatureRequests')
            ? $contract->signatureRequests->sortByDesc('created_at')->first()
            : null;

        return array_merge(self::contractSummary($contract), [
            'description' => $contract->description ? (string) $contract->description : null,
            'content_excerpt' => $contract->content_html ? Str::limit(trim(strip_tags((string) $contract->content_html)), 280) : null,
            'billing_type' => $contract->billing_type ? (string) $contract->billing_type : null,
            'billing_type_label' => $contract->billing_type
                ? (ContractCatalog::billingTypes()[$contract->billing_type] ?? Str::headline((string) $contract->billing_type))
                : null,
            'recurrence' => $contract->recurrence ? (string) $contract->recurrence : null,
            'recurrence_label' => $contract->recurrence
                ? (ContractCatalog::recurrences()[$contract->recurrence] ?? Str::headline((string) $contract->recurrence))
                : null,
            'responsible_name' => $contract->responsible?->name,
            'condominium_name' => $contract->condominium?->name,
            'syndic_name' => $contract->syndic?->display_name ?: $contract->condominium?->syndic?->display_name,
            'financial_account_name' => $contract->financialAccount?->name,
            'proposal_code' => $contract->proposal?->proposal_code,
            'process_number' => $contract->process?->process_number,
            'documents_count' => 1
                + ($contract->relationLoaded('versions') ? $contract->versions->count() : 0)
                + ($contract->relationLoaded('attachments') ? $contract->attachments->count() : 0),
            'latest_signature' => $latestSignature ? self::signatureSummary($latestSignature) : null,
        ]);
    }

    public static function contractDocument(
        int|string $id,
        string $kind,
        string $kindLabel,
        string $name,
        ?string $mimeType,
        int $fileSize,
        ?string $createdAt,
        ?string $createdAtBr,
        ?string $description = null,
        ?int $referenceId = null,
    ): array {
        return [
            'id' => (string) $id,
            'kind' => $kind,
            'kind_label' => $kindLabel,
            'name' => $name,
            'description' => $description,
            'mime_type' => $mimeType,
            'file_size' => $fileSize,
            'file_size_label' => self::fileSize($fileSize),
            'created_at' => $createdAt,
            'created_at_br' => $createdAtBr,
            'download_kind' => $kind,
            'reference_id' => $referenceId,
        ];
    }

    public static function contractMainDocument(Contract $contract): ?array
    {
        if (trim((string) $contract->final_pdf_path) === '') {
            return null;
        }

        $name = basename((string) $contract->final_pdf_path) ?: ((string) ($contract->code ?: 'contrato') . '.pdf');

        return self::contractDocument(
            id: 'main',
            kind: 'main',
            kindLabel: 'PDF principal',
            name: $name,
            mimeType: 'application/pdf',
            fileSize: 0,
            createdAt: $contract->final_pdf_generated_at?->toAtomString(),
            createdAtBr: $contract->final_pdf_generated_at?->format('d/m/Y H:i'),
            description: 'Versão final do contrato para visualização e download.',
            referenceId: null,
        );
    }

    public static function contractVersionDocument(ContractVersion $version): array
    {
        return self::contractDocument(
            id: 'version-' . $version->id,
            kind: 'version',
            kindLabel: 'Versão',
            name: 'Versão v' . $version->version_number . '.pdf',
            mimeType: 'application/pdf',
            fileSize: 0,
            createdAt: $version->generated_at?->toAtomString(),
            createdAtBr: $version->generated_at?->format('d/m/Y H:i'),
            description: $version->notes ? (string) $version->notes : 'PDF gerado do contrato.',
            referenceId: (int) $version->id,
        );
    }

    public static function contractAttachmentDocument(ContractAttachment $attachment): array
    {
        return self::contractDocument(
            id: 'attachment-' . $attachment->id,
            kind: 'attachment',
            kindLabel: 'Anexo',
            name: (string) $attachment->original_name,
            mimeType: $attachment->mime_type ? (string) $attachment->mime_type : 'application/octet-stream',
            fileSize: (int) ($attachment->file_size ?? 0),
            createdAt: $attachment->created_at?->toAtomString(),
            createdAtBr: $attachment->created_at?->format('d/m/Y H:i'),
            description: $attachment->description ? (string) $attachment->description : (self::attachmentTypeLabel($attachment->file_type) ?: 'Documento complementar do contrato.'),
            referenceId: (int) $attachment->id,
        );
    }

    public static function signatureStatusOptions(): array
    {
        return collect(DocumentSignatureService::requestStatusLabels())
            ->map(fn (string $label, string $value) => [
                'value' => $value,
                'label' => $label,
            ])
            ->values()
            ->all();
    }

    public static function signatureOriginOptions(): array
    {
        return [
            ['value' => 'contrato', 'label' => 'Contrato'],
            ['value' => 'cobranca', 'label' => 'Cobrança'],
            ['value' => 'avulso', 'label' => 'Avulso'],
        ];
    }

    public static function signatureSummary(DocumentSignatureRequest $signature): array
    {
        $signers = $signature->relationLoaded('signers') ? $signature->signers : collect();
        $pendingCount = $signers->filter(fn (DocumentSignatureSigner $item) => !in_array((string) $item->status, ['signed', 'rejected'], true))->count();

        return [
            'id' => (int) $signature->id,
            'document_name' => (string) ($signature->document_name ?: ('Assinatura #' . $signature->id)),
            'status' => (string) $signature->status,
            'status_label' => DocumentSignatureService::requestStatusLabels()[$signature->status] ?? Str::headline((string) $signature->status),
            'source_type' => self::signatureSourceType($signature),
            'source_label' => self::signatureSourceLabel($signature),
            'signers_count' => $signers->count(),
            'pending_count' => $pendingCount,
            'created_at' => $signature->created_at?->toAtomString(),
            'created_at_br' => $signature->created_at?->format('d/m/Y H:i'),
            'completed_at' => $signature->completed_at?->toAtomString(),
            'completed_at_br' => $signature->completed_at?->format('d/m/Y H:i'),
            'updated_at' => $signature->updated_at?->toAtomString(),
            'updated_at_br' => $signature->updated_at?->format('d/m/Y H:i'),
        ];
    }

    public static function signatureDetail(DocumentSignatureRequest $signature, bool $canSync = false): array
    {
        return array_merge(self::signatureSummary($signature), [
            'requested_at' => $signature->requested_at?->toAtomString(),
            'requested_at_br' => $signature->requested_at?->format('d/m/Y H:i'),
            'last_synced_at' => $signature->last_synced_at?->toAtomString(),
            'last_synced_at_br' => $signature->last_synced_at?->format('d/m/Y H:i'),
            'provider' => $signature->provider ? (string) $signature->provider : null,
            'signing_url_available' => trim((string) $signature->signing_url) !== '',
            'summary' => is_array($signature->summary_json) ? $signature->summary_json : [],
            'signers' => $signature->relationLoaded('signers')
                ? $signature->signers->map(fn (DocumentSignatureSigner $item) => self::signatureSigner($item))->values()->all()
                : [],
            'events' => $signature->relationLoaded('events')
                ? $signature->events->map(fn (DocumentSignatureEvent $item) => self::signatureEvent($item))->values()->all()
                : [],
            'available_actions' => [
                'can_sync' => $canSync,
            ],
        ]);
    }

    public static function signatureSigner(DocumentSignatureSigner $signer): array
    {
        $status = (string) ($signer->status ?: 'pending');

        return [
            'id' => (int) $signer->id,
            'name' => (string) $signer->name,
            'email' => $signer->email ? (string) $signer->email : null,
            'phone' => $signer->phone ? (string) $signer->phone : null,
            'role_label' => $signer->role_label ? (string) $signer->role_label : null,
            'status' => $status,
            'status_label' => DocumentSignatureService::signerStatusLabels()[$status] ?? Str::headline($status),
            'requested_at_br' => $signer->requested_at?->format('d/m/Y H:i'),
            'viewed_at_br' => $signer->viewed_at?->format('d/m/Y H:i'),
            'signed_at_br' => $signer->signed_at?->format('d/m/Y H:i'),
            'rejected_at_br' => $signer->rejected_at?->format('d/m/Y H:i'),
        ];
    }

    public static function signatureEvent(DocumentSignatureEvent $event): array
    {
        $label = $event->event_type ? Str::headline(str_replace('_', ' ', (string) $event->event_type)) : 'Evento';

        return [
            'id' => (int) $event->id,
            'event_type' => $event->event_type ? (string) $event->event_type : null,
            'label' => $label,
            'signer_name' => $event->signer?->name,
            'description' => $event->description ? (string) $event->description : $label,
            'received_at' => $event->received_at?->toAtomString(),
            'received_at_br' => $event->received_at?->format('d/m/Y H:i'),
        ];
    }

    public static function financeDashboard(
        array $summary,
        array $cards,
        array $alerts,
        array $cashflowPreview,
    ): array {
        return [
            'summary' => $summary,
            'cards' => $cards,
            'alerts' => $alerts,
            'cashflow_preview' => $cashflowPreview,
            'updated_at' => now()->toAtomString(),
            'updated_at_br' => now()->format('d/m/Y H:i'),
        ];
    }

    public static function financeReceivable(FinancialReceivable $item): array
    {
        $outstanding = max(0, (float) $item->final_amount - (float) $item->received_amount);

        return [
            'id' => (int) $item->id,
            'code' => (string) ($item->code ?: ('REC-' . $item->id)),
            'title' => (string) ($item->title ?: 'Conta a receber'),
            'client_name' => $item->client?->display_name,
            'condominium_name' => $item->condominium?->name,
            'contract_code' => $item->contract?->code,
            'status' => (string) ($item->status ?: 'aberto'),
            'status_label' => FinancialCatalog::receivableStatuses()[$item->status ?: 'aberto'] ?? Str::headline((string) ($item->status ?: 'aberto')),
            'due_date' => $item->due_date?->toDateString(),
            'due_date_br' => $item->due_date?->format('d/m/Y'),
            'amount' => $item->final_amount !== null ? (float) $item->final_amount : null,
            'amount_label' => self::money($item->final_amount),
            'received_amount' => $item->received_amount !== null ? (float) $item->received_amount : null,
            'received_amount_label' => self::money($item->received_amount),
            'outstanding_amount' => $outstanding,
            'outstanding_amount_label' => self::money($outstanding),
            'updated_at_br' => $item->updated_at?->format('d/m/Y H:i'),
        ];
    }

    public static function financePayable(FinancialPayable $item): array
    {
        $outstanding = max(0, (float) $item->amount - (float) $item->paid_amount);

        return [
            'id' => (int) $item->id,
            'code' => (string) ($item->code ?: ('PAG-' . $item->id)),
            'title' => (string) ($item->title ?: 'Conta a pagar'),
            'supplier_name' => $item->supplier?->display_name,
            'status' => (string) ($item->status ?: 'aberto'),
            'status_label' => FinancialCatalog::payableStatuses()[$item->status ?: 'aberto'] ?? Str::headline((string) ($item->status ?: 'aberto')),
            'due_date' => $item->due_date?->toDateString(),
            'due_date_br' => $item->due_date?->format('d/m/Y'),
            'amount' => $item->amount !== null ? (float) $item->amount : null,
            'amount_label' => self::money($item->amount),
            'paid_amount' => $item->paid_amount !== null ? (float) $item->paid_amount : null,
            'paid_amount_label' => self::money($item->paid_amount),
            'outstanding_amount' => $outstanding,
            'outstanding_amount_label' => self::money($outstanding),
            'updated_at_br' => $item->updated_at?->format('d/m/Y H:i'),
        ];
    }

    public static function financeCashflow(FinancialTransaction $item): array
    {
        $type = (string) ($item->transaction_type ?: 'entrada');

        return [
            'id' => (int) $item->id,
            'description' => (string) ($item->description ?: $item->source ?: 'Movimentação financeira'),
            'transaction_type' => $type,
            'transaction_type_label' => FinancialCatalog::transactionTypes()[$type] ?? Str::headline($type),
            'amount' => $item->amount !== null ? (float) $item->amount : null,
            'amount_label' => self::money($item->amount),
            'transaction_date' => $item->transaction_date?->toAtomString(),
            'transaction_date_br' => $item->transaction_date?->format('d/m/Y H:i'),
            'account_name' => $item->account?->name,
            'category_name' => $item->category?->name,
            'document_number' => $item->document_number ? (string) $item->document_number : null,
            'reconciliation_status' => $item->reconciliation_status ? (string) $item->reconciliation_status : null,
        ];
    }

    public static function money(float|int|string|null $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return 'R$ ' . number_format((float) $value, 2, ',', '.');
    }

    public static function fileSize(int|float|string|null $bytes): string
    {
        $bytes = max(0, (int) ($bytes ?? 0));
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1, ',', '.') . ' KB';
        }

        return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
    }

    public static function financeSummaryCard(string $key, string $title, float $value, string $description, string $tone = 'brand'): array
    {
        return [
            'key' => $key,
            'title' => $title,
            'value' => $value,
            'value_label' => self::money($value),
            'description' => $description,
            'tone' => $tone,
        ];
    }

    public static function financeAlert(string $title, string $message, string $tone = 'warning'): array
    {
        return [
            'title' => $title,
            'message' => $message,
            'tone' => $tone,
        ];
    }

    private static function contractValue(Contract $contract): float
    {
        return (float) ($contract->contract_value ?? $contract->total_value ?? $contract->monthly_value ?? 0);
    }

    private static function contractExpired(Contract $contract): bool
    {
        if ((bool) $contract->indefinite_term) {
            return false;
        }

        return $contract->end_date !== null && $contract->end_date->isPast();
    }

    private static function attachmentTypeLabel(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        return ContractCatalog::fileTypes()[$value] ?? Str::headline(str_replace('_', ' ', $value));
    }

    private static function signatureSourceType(DocumentSignatureRequest $signature): string
    {
        return match ($signature->signable_type) {
            Contract::class => 'contrato',
            \App\Models\CobrancaCase::class => 'cobranca',
            default => 'avulso',
        };
    }

    private static function signatureSourceLabel(DocumentSignatureRequest $signature): string
    {
        return match ($signature->signable_type) {
            Contract::class => 'Contrato',
            \App\Models\CobrancaCase::class => 'Cobrança',
            default => 'Avulso',
        };
    }
}
