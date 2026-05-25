<?php

namespace App\Support\Hub;

use App\Models\CobrancaCase;
use App\Models\CobrancaCaseAttachment;
use App\Models\CobrancaCaseInstallment;
use App\Models\CobrancaCaseQuota;
use App\Models\CobrancaCaseTimeline;
use App\Models\Demand;
use App\Models\DemandAttachment;
use App\Models\DemandMessage;
use App\Models\ProcessCase;
use App\Models\ProcessCaseAttachment;
use App\Models\ProcessCaseParty;
use App\Models\ProcessCasePhase;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class HubModulePresenter
{
    public static function pagination(LengthAwarePaginator $items): array
    {
        return [
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'per_page' => $items->perPage(),
            'total' => $items->total(),
        ];
    }

    public static function demandStatusLabels(): array
    {
        return [
            'aberta' => 'Aberta',
            'em_triagem' => 'Em triagem',
            'em_andamento' => 'Em andamento',
            'aguardando_cliente' => 'Aguardando cliente',
            'aguardando_formalizacao_acordo' => 'Aguardando formalização do acordo',
            'concluida' => 'Concluída',
            'cancelada' => 'Cancelada',
        ];
    }

    public static function demandPriorityLabels(): array
    {
        return [
            'baixa' => 'Baixa',
            'normal' => 'Normal',
            'alta' => 'Alta',
            'urgente' => 'Urgente',
        ];
    }

    public static function demandSummary(Demand $demand): array
    {
        return [
            'id' => (int) $demand->id,
            'protocol' => (string) ($demand->protocol ?: ('DEM-' . $demand->id)),
            'title' => (string) $demand->subject,
            'client_name' => $demand->clientName(),
            'condominium_name' => $demand->condominium?->name,
            'status' => (string) $demand->status,
            'status_label' => $demand->tag?->name ?: (self::demandStatusLabels()[$demand->status] ?? (string) $demand->status),
            'priority' => (string) ($demand->priority ?: 'normal'),
            'priority_label' => self::demandPriorityLabels()[$demand->priority ?: 'normal'] ?? 'Normal',
            'category_name' => $demand->category?->name,
            'assignee' => $demand->assignee ? self::assignee($demand->assignee) : null,
            'sla' => [
                'status' => $demand->slaStatus(),
                'label' => $demand->slaStatusLabel(),
                'due_at' => $demand->sla_due_at?->toAtomString(),
                'due_at_br' => $demand->sla_due_at?->format('d/m/Y H:i'),
                'progress_percent' => $demand->slaProgressPercent(),
            ],
            'messages_count' => (int) ($demand->messages_count ?? $demand->messages()->count()),
            'attachments_count' => (int) ($demand->attachments_count ?? $demand->attachments()->count()),
            'created_at' => $demand->created_at?->toAtomString(),
            'created_at_br' => $demand->created_at?->format('d/m/Y H:i'),
            'updated_at' => $demand->updated_at?->toAtomString(),
            'updated_at_br' => $demand->updated_at?->format('d/m/Y H:i'),
        ];
    }

    public static function demandDetail(Demand $demand, array $actions = [], array $options = []): array
    {
        $attachments = $demand->relationLoaded('attachments')
            ? $demand->attachments->map(fn (DemandAttachment $attachment) => self::demandAttachment($attachment))->values()->all()
            : [];

        $messages = $demand->relationLoaded('messages')
            ? $demand->messages->map(fn (DemandMessage $message) => self::demandMessage($message))->values()->all()
            : [];

        return array_merge(self::demandSummary($demand), [
            'description' => (string) ($demand->description ?: ''),
            'requester' => [
                'name' => $demand->portalUser?->name ?: ($demand->entity?->display_name ?: 'Não informado'),
                'email' => $demand->portalUser?->email,
            ],
            'entity_name' => $demand->entity?->display_name,
            'messages' => $messages,
            'attachments' => $attachments,
            'available_actions' => array_merge([
                'can_reply' => false,
                'can_update_status' => false,
                'can_move' => false,
                'can_assign' => false,
            ], $actions),
            'status_options' => $options['status_options'] ?? [],
            'tag_options' => $options['tag_options'] ?? [],
            'assignees' => $options['assignees'] ?? [],
            'closed_at' => $demand->closed_at?->toAtomString(),
            'closed_at_br' => $demand->closed_at?->format('d/m/Y H:i'),
        ]);
    }

    public static function demandMessage(DemandMessage $message): array
    {
        return [
            'id' => (int) $message->id,
            'sender_type' => (string) $message->sender_type,
            'sender_name' => $message->senderName(),
            'is_internal' => (bool) $message->is_internal,
            'message' => (string) $message->message,
            'created_at' => $message->created_at?->toAtomString(),
            'created_at_br' => $message->created_at?->format('d/m/Y H:i'),
            'attachments' => $message->relationLoaded('attachments')
                ? $message->attachments->map(fn (DemandAttachment $attachment) => self::demandAttachment($attachment))->values()->all()
                : [],
        ];
    }

    public static function demandAttachment(DemandAttachment $attachment): array
    {
        return [
            'id' => (int) $attachment->id,
            'name' => (string) $attachment->original_name,
            'mime_type' => $attachment->mime_type ? (string) $attachment->mime_type : null,
            'file_size' => (int) ($attachment->file_size ?? 0),
            'is_internal' => (bool) $attachment->is_internal,
            'created_at' => $attachment->created_at?->toAtomString(),
            'created_at_br' => $attachment->created_at?->format('d/m/Y H:i'),
        ];
    }

    public static function assignee(User $user): array
    {
        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'initials' => (string) $user->initials,
        ];
    }

    public static function statusOption(string $value, string $label): array
    {
        return [
            'value' => $value,
            'label' => $label,
        ];
    }

    public static function processSummary(ProcessCase $case): array
    {
        return [
            'id' => (int) $case->id,
            'process_number' => (string) ($case->process_number ?: ('Processo #' . $case->id)),
            'client_name' => (string) ($case->client_name_snapshot ?: $case->client?->display_name ?: 'Não informado'),
            'condominium_name' => $case->clientCondominium?->name,
            'class_name' => $case->processTypeOption?->name,
            'subject_name' => $case->natureOption?->name,
            'status' => $case->statusOption?->name,
            'responsible_lawyer' => $case->responsible_lawyer ? (string) $case->responsible_lawyer : null,
            'last_movement_at' => $case->latest_phase_at?->toAtomString(),
            'last_movement_at_br' => $case->latest_phase_at?->format('d/m/Y'),
            'last_movement_description' => $case->latest_phase_description ? (string) $case->latest_phase_description : null,
            'is_private' => (bool) $case->is_private,
            'created_at' => $case->created_at?->toAtomString(),
            'updated_at' => $case->updated_at?->toAtomString(),
            'updated_at_br' => $case->updated_at?->format('d/m/Y H:i'),
        ];
    }

    public static function processDetail(ProcessCase $case): array
    {
        return array_merge(self::processSummary($case), [
            'opened_at' => $case->opened_at?->toDateString(),
            'opened_at_br' => $case->opened_at?->format('d/m/Y'),
            'closed_at' => $case->closed_at?->toDateString(),
            'closed_at_br' => $case->closed_at?->format('d/m/Y'),
            'notes' => $case->notes ? (string) $case->notes : null,
            'judging_body' => $case->judging_body ? (string) $case->judging_body : null,
            'action_type' => $case->actionTypeOption?->name,
            'client_position' => $case->clientPositionOption?->name,
            'adverse_position' => $case->adversePositionOption?->name,
            'win_probability' => $case->winProbabilityOption?->name,
            'closure_type' => $case->closureTypeOption?->name,
            'client' => [
                'name' => (string) ($case->client_name_snapshot ?: $case->client?->display_name ?: 'Não informado'),
                'condominium_name' => $case->clientCondominium?->name,
                'syndic_name' => $case->clientCondominium?->syndic?->display_name,
            ],
            'adverse' => [
                'name' => (string) ($case->adverse_name ?: $case->adverse?->display_name ?: 'Não informado'),
                'condominium_name' => $case->adverseCondominium?->name,
            ],
            'parties' => $case->relationLoaded('parties')
                ? $case->parties->map(fn (ProcessCaseParty $party) => self::processParty($party))->values()->all()
                : [],
            'movements_preview' => $case->relationLoaded('phases')
                ? $case->phases->take(5)->map(fn (ProcessCasePhase $phase) => self::processMovement($phase))->values()->all()
                : [],
            'attachments_preview' => $case->relationLoaded('attachments')
                ? $case->attachments->take(5)->map(fn (ProcessCaseAttachment $attachment) => self::processAttachment($attachment))->values()->all()
                : [],
        ]);
    }

    public static function processParty(ProcessCaseParty $party): array
    {
        return [
            'id' => (int) $party->id,
            'party_type' => (string) $party->party_type,
            'name' => (string) ($party->name_snapshot ?: $party->entity?->display_name ?: 'Não informado'),
            'document' => $party->document_snapshot ? (string) $party->document_snapshot : null,
            'side_label' => $party->party_type === 'adverse' ? 'Polo adverso' : 'Cliente',
        ];
    }

    public static function processMovement(ProcessCasePhase $phase): array
    {
        return [
            'id' => (int) $phase->id,
            'description' => (string) $phase->description,
            'phase_date' => $phase->phase_date?->toDateString(),
            'phase_date_br' => $phase->phase_date?->format('d/m/Y'),
            'phase_time' => $phase->phase_time ? (string) $phase->phase_time : null,
            'notes' => $phase->notes ? (string) $phase->notes : null,
            'legal_opinion' => $phase->legal_opinion ? (string) $phase->legal_opinion : null,
            'conference' => $phase->conference ? (string) $phase->conference : null,
            'is_reviewed' => (bool) $phase->is_reviewed,
            'source' => $phase->source ? (string) $phase->source : 'manual',
            'created_by_name' => $phase->creator?->name,
            'attachments' => $phase->relationLoaded('attachments')
                ? $phase->attachments->map(fn (ProcessCaseAttachment $attachment) => self::processAttachment($attachment))->values()->all()
                : [],
        ];
    }

    public static function processAttachment(ProcessCaseAttachment $attachment): array
    {
        return [
            'id' => (int) $attachment->id,
            'name' => (string) $attachment->original_name,
            'mime_type' => $attachment->mime_type ? (string) $attachment->mime_type : null,
            'file_size' => (int) ($attachment->file_size ?? 0),
            'file_role' => $attachment->file_role ? (string) $attachment->file_role : null,
            'phase_id' => $attachment->phase_id ? (int) $attachment->phase_id : null,
            'uploaded_by_name' => $attachment->uploader?->name,
            'created_at' => $attachment->created_at?->toAtomString(),
            'created_at_br' => $attachment->created_at?->format('d/m/Y H:i'),
        ];
    }

    public static function collectionWorkflowStageLabels(): array
    {
        return [
            'triagem' => 'Triagem',
            'apto_notificar' => 'Apto para notificar',
            'em_negociacao' => 'Em negociação',
            'sem_retorno' => 'Sem retorno',
            'aguardando_termo' => 'Aguardando termo',
            'aguardando_assinatura' => 'Aguardando assinatura',
            'acordo_ativo' => 'Acordo ativo',
            'aguardando_boletos' => 'Aguardando boletos',
            'acordo_inadimplido' => 'Acordo inadimplido',
            'apto_judicializar' => 'Apto para judicialização',
            'judicializado' => 'Judicializado',
            'pago_encerrado' => 'Pago / encerrado',
            'cancelado' => 'Cancelado',
            'notificado' => 'Notificado',
        ];
    }

    public static function collectionSituationLabels(): array
    {
        return [
            'acordo_nao_pago' => 'Acordo não pago',
            'acordo_renegociado' => 'Acordo renegociado',
            'ajuizado' => 'Ajuizado',
            'cancelado' => 'Cancelado',
            'em_pagamento_acordo' => 'Em pagamento de acordo',
            'pago_encerrado' => 'Pago / encerrado',
            'processo_aberto' => 'Processo de cobrança em aberto',
        ];
    }

    public static function collectionBillingStatusLabels(): array
    {
        return [
            'a_faturar' => 'A faturar',
            'faturado' => 'Faturado',
            'contrato_fixo' => 'Contrato fixo',
            'cancelado' => 'Cancelado',
        ];
    }

    public static function collectionSummary(CobrancaCase $case): array
    {
        return [
            'id' => (int) $case->id,
            'os_number' => (string) ($case->os_number ?: ('OS #' . $case->id)),
            'condominium_id' => $case->condominium_id ? (int) $case->condominium_id : null,
            'unit_id' => $case->unit_id ? (int) $case->unit_id : null,
            'condominium_name' => $case->condominium?->name ?: 'Condomínio não informado',
            'unit_label' => (string) ($case->unit?->unit_label ?: $case->unit?->unit_number ?: 'Sem unidade'),
            'debtor_name' => (string) ($case->debtor_name_snapshot ?: $case->debtor?->display_name ?: 'Não informado'),
            'owner_name' => $case->unit?->owner?->display_name,
            'tenant_name' => $case->unit?->tenant?->display_name,
            'agreement_total' => $case->agreement_total !== null ? (float) $case->agreement_total : null,
            'agreement_total_label' => $case->agreement_total !== null ? self::money((float) $case->agreement_total) : null,
            'workflow_stage' => (string) ($case->workflow_stage ?: 'triagem'),
            'workflow_stage_label' => self::collectionWorkflowStageLabels()[$case->workflow_stage ?: 'triagem'] ?? (string) $case->workflow_stage,
            'situation' => $case->situation ? (string) $case->situation : null,
            'situation_label' => $case->situation ? (self::collectionSituationLabels()[$case->situation] ?? (string) $case->situation) : null,
            'billing_status' => $case->billing_status ? (string) $case->billing_status : null,
            'billing_status_label' => $case->billing_status ? (self::collectionBillingStatusLabels()[$case->billing_status] ?? (string) $case->billing_status) : null,
            'last_progress_at' => $case->last_progress_at?->toAtomString(),
            'last_progress_at_br' => $case->last_progress_at?->format('d/m/Y H:i'),
            'updated_at' => $case->updated_at?->toAtomString(),
            'updated_at_br' => $case->updated_at?->format('d/m/Y H:i'),
        ];
    }

    public static function collectionDetail(CobrancaCase $case): array
    {
        return array_merge(self::collectionSummary($case), [
            'charge_type' => $case->charge_type ? (string) $case->charge_type : null,
            'case_mode' => $case->case_mode ? (string) $case->case_mode : null,
            'debtor_document' => $case->debtor_document_snapshot ? (string) $case->debtor_document_snapshot : null,
            'entry_amount' => $case->entry_amount !== null ? (float) $case->entry_amount : null,
            'entry_amount_label' => $case->entry_amount !== null ? self::money((float) $case->entry_amount) : null,
            'fees_amount' => $case->fees_amount !== null ? (float) $case->fees_amount : null,
            'fees_amount_label' => $case->fees_amount !== null ? self::money((float) $case->fees_amount) : null,
            'billing_date' => $case->billing_date?->toDateString(),
            'billing_date_br' => $case->billing_date?->format('d/m/Y'),
            'judicial_case_number' => $case->judicial_case_number ? (string) $case->judicial_case_number : null,
            'syndic_name' => $case->condominium?->syndic?->display_name,
            'administrator_name' => $case->condominium?->administradora?->display_name,
            'contacts' => $case->relationLoaded('contacts')
                ? $case->contacts->map(function ($contact) {
                    return [
                        'id' => (int) $contact->id,
                        'type' => (string) $contact->contact_type,
                        'value' => (string) $contact->value,
                        'is_primary' => (bool) $contact->is_primary,
                        'is_whatsapp' => (bool) ($contact->is_whatsapp ?? false),
                    ];
                })->values()->all()
                : [],
            'quotas' => $case->relationLoaded('quotas')
                ? $case->quotas->map(fn (CobrancaCaseQuota $quota) => self::collectionQuota($quota))->values()->all()
                : [],
            'agreement' => [
                'has_term' => $case->agreementTerm !== null,
                'has_signature_requests' => $case->relationLoaded('signatureRequests')
                    ? $case->signatureRequests->isNotEmpty()
                    : false,
                'signature_requests_count' => $case->relationLoaded('signatureRequests')
                    ? $case->signatureRequests->count()
                    : 0,
            ],
        ]);
    }

    public static function collectionQuota(CobrancaCaseQuota $quota): array
    {
        return [
            'id' => (int) $quota->id,
            'reference_label' => (string) ($quota->reference_label ?: 'Sem referência'),
            'due_date' => $quota->due_date?->toDateString(),
            'due_date_br' => $quota->due_date?->format('d/m/Y'),
            'status' => $quota->status ? (string) $quota->status : null,
            'original_amount' => $quota->original_amount !== null ? (float) $quota->original_amount : null,
            'original_amount_label' => $quota->original_amount !== null ? self::money((float) $quota->original_amount) : null,
            'updated_amount' => $quota->updated_amount !== null ? (float) $quota->updated_amount : null,
            'updated_amount_label' => $quota->updated_amount !== null ? self::money((float) $quota->updated_amount) : null,
        ];
    }

    public static function collectionInstallment(CobrancaCaseInstallment $installment): array
    {
        return [
            'id' => (int) $installment->id,
            'label' => (string) ($installment->label ?: ('Parcela ' . ($installment->installment_number ?: $installment->id))),
            'installment_type' => $installment->installment_type ? (string) $installment->installment_type : null,
            'installment_number' => $installment->installment_number ? (int) $installment->installment_number : null,
            'due_date' => $installment->due_date?->toDateString(),
            'due_date_br' => $installment->due_date?->format('d/m/Y'),
            'amount' => $installment->amount !== null ? (float) $installment->amount : null,
            'amount_label' => $installment->amount !== null ? self::money((float) $installment->amount) : null,
            'status' => $installment->status ? (string) $installment->status : null,
        ];
    }

    public static function collectionTimeline(CobrancaCaseTimeline $timeline): array
    {
        return [
            'id' => (int) $timeline->id,
            'event_type' => $timeline->event_type ? (string) $timeline->event_type : null,
            'description' => (string) $timeline->description,
            'user_name' => $timeline->user?->name,
            'created_at' => $timeline->created_at?->toAtomString(),
            'created_at_br' => $timeline->created_at?->format('d/m/Y H:i'),
        ];
    }

    public static function collectionAttachment(CobrancaCaseAttachment $attachment): array
    {
        return [
            'id' => (int) $attachment->id,
            'name' => (string) $attachment->original_name,
            'mime_type' => $attachment->mime_type ? (string) $attachment->mime_type : null,
            'file_size' => (int) ($attachment->file_size ?? 0),
            'uploaded_by_name' => $attachment->uploader?->name,
            'created_at' => $attachment->created_at?->toAtomString(),
            'created_at_br' => $attachment->created_at?->format('d/m/Y H:i'),
        ];
    }

    private static function money(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }
}
