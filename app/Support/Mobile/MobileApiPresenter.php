<?php

namespace App\Support\Mobile;

use App\Models\AiChatConversation;
use App\Models\AiChatMessage;
use App\Models\ClientCondominium;
use App\Models\ClientPortalNotification;
use App\Models\ClientPortalUser;
use App\Models\Demand;
use App\Models\DemandAttachment;
use App\Models\DemandMessage;
use App\Models\ProcessCase;
use App\Models\ProcessCasePhase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use DateTimeInterface;

class MobileApiPresenter
{
    public static function authPayload(
        ClientPortalUser $user,
        string $plainTextToken,
        ?ClientCondominium $selectedCondominium,
        DateTimeInterface|string|null $expiresAt = null,
    ): array {
        return [
            'token_type' => 'Bearer',
            'access_token' => $plainTextToken,
            'expires_at' => $expiresAt instanceof DateTimeInterface ? $expiresAt->format(DATE_ATOM) : ($expiresAt ? (string) $expiresAt : null),
            'user' => self::user($user, $selectedCondominium),
        ];
    }

    public static function user(ClientPortalUser $user, ?ClientCondominium $selectedCondominium = null): array
    {
        return [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'login_key' => (string) $user->login_key,
            'email' => $user->email ? (string) $user->email : null,
            'portal_role' => (string) ($user->portal_role ?? 'cliente'),
            'must_change_password' => (bool) $user->must_change_password,
            'permissions' => [
                'can_view_processes' => (bool) $user->can_view_processes,
                'can_view_cobrancas' => (bool) $user->can_view_cobrancas,
                'can_open_demands' => (bool) $user->can_open_demands,
                'can_view_demands' => (bool) $user->can_view_demands,
                'can_view_documents' => (bool) $user->can_view_documents,
                'can_view_financial_summary' => (bool) $user->can_view_financial_summary,
                'ai_enabled' => (bool) $user->ai_enabled,
            ],
            'selected_condominium' => $selectedCondominium ? self::condominium($selectedCondominium) : null,
            'accessible_condominiums' => self::condominiums($user->accessibleCondominiums()),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public static function condominiums(iterable $items): array
    {
        return collect($items)->map(fn (ClientCondominium $item) => self::condominium($item))->values()->all();
    }

    public static function condominium(ClientCondominium $item): array
    {
        return [
            'id' => (int) $item->id,
            'name' => (string) $item->name,
            'syndic_name' => $item->syndic?->display_name ? (string) $item->syndic->display_name : null,
            'administradora_name' => $item->administradora?->display_name ? (string) $item->administradora->display_name : null,
            'type' => $item->type?->name ? (string) $item->type->name : null,
        ];
    }

    public static function processSummary(ProcessCase $case): array
    {
        $lastPhase = $case->phases->first();
        $clientName = self::processClientName($case);
        $adverseName = self::processAdverseName($case);

        return [
            'id' => (int) $case->id,
            'process_number' => (string) ($case->process_number ?: ('Processo #' . $case->id)),
            'client_name' => $clientName,
            'adverse_name' => $adverseName,
            'parties_label' => self::processPartiesLabel($clientName, $adverseName),
            'status' => [
                'label' => (string) ($case->statusOption?->name ?: 'Sem status'),
                'color' => (string) ($case->statusOption?->color_hex ?: '#6B7280'),
            ],
            'type' => $case->processTypeOption?->name ? (string) $case->processTypeOption->name : null,
            'nature' => $case->natureOption?->name ? (string) $case->natureOption->name : null,
            'last_public_phase' => $lastPhase ? self::processPhase($lastPhase) : null,
            'updated_at' => $case->updated_at?->toAtomString(),
        ];
    }

    public static function processDetail(ProcessCase $case): array
    {
        $clientName = self::processClientName($case);
        $adverseName = self::processAdverseName($case);

        return [
            'id' => (int) $case->id,
            'process_number' => (string) ($case->process_number ?: ('Processo #' . $case->id)),
            'client_name' => $clientName,
            'adverse_name' => $adverseName,
            'parties_label' => self::processPartiesLabel($clientName, $adverseName),
            'status' => [
                'label' => (string) ($case->statusOption?->name ?: 'Sem status'),
                'color' => (string) ($case->statusOption?->color_hex ?: '#6B7280'),
            ],
            'type' => $case->processTypeOption?->name ? (string) $case->processTypeOption->name : null,
            'nature' => $case->natureOption?->name ? (string) $case->natureOption->name : null,
            'court' => $case->datajud_court ? (string) $case->datajud_court : null,
            'phases' => $case->phases->map(fn (ProcessCasePhase $phase) => self::processPhase($phase))->values()->all(),
            'updated_at' => $case->updated_at?->toAtomString(),
        ];
    }

    public static function processPhase(ProcessCasePhase $phase): array
    {
        return [
            'id' => (int) $phase->id,
            'description' => (string) $phase->description,
            'source' => (string) $phase->source,
            'source_label' => $phase->source === 'datajud' ? 'Movimentacao DataJud' : 'Andamento informado pelo escritorio',
            'phase_date' => $phase->phase_date?->toDateString(),
            'phase_date_br' => $phase->phase_date?->format('d/m/Y'),
            'created_at' => $phase->created_at?->toAtomString(),
        ];
    }

    public static function demandSummary(Demand $demand): array
    {
        return [
            'id' => (int) $demand->id,
            'protocol' => (string) $demand->protocol,
            'subject' => (string) $demand->subject,
            'category' => $demand->category?->name ? (string) $demand->category->name : null,
            'status' => [
                'key' => (string) $demand->status,
                'label' => (string) $demand->publicStatusLabel(),
                'tag' => $demand->tag?->name ? (string) $demand->tag->name : null,
                'color' => (string) ($demand->tag?->color_hex ?: '#941415'),
            ],
            'updated_at' => $demand->updated_at?->toAtomString(),
            'updated_at_br' => $demand->updated_at?->format('d/m/Y H:i'),
            'has_new_response' => (string) $demand->status === 'aguardando_cliente',
            'client_condominium' => $demand->condominium ? self::condominium($demand->condominium) : null,
        ];
    }

    public static function demandDetail(Request $request, Demand $demand, bool $canManageDemand): array
    {
        return [
            'id' => (int) $demand->id,
            'protocol' => (string) $demand->protocol,
            'subject' => (string) $demand->subject,
            'description' => (string) $demand->description,
            'category' => $demand->category?->name ? (string) $demand->category->name : null,
            'status' => [
                'key' => (string) $demand->status,
                'label' => (string) $demand->publicStatusLabel(),
                'tag' => $demand->tag?->name ? (string) $demand->tag->name : null,
                'color' => (string) ($demand->tag?->color_hex ?: '#941415'),
            ],
            'client_condominium' => $demand->condominium ? self::condominium($demand->condominium) : null,
            'can_manage' => $canManageDemand,
            'can_cancel' => $canManageDemand,
            'can_reply' => !in_array($demand->status, ['concluida', 'cancelada'], true),
            'messages' => $demand->publicMessages->map(fn (DemandMessage $message) => self::demandMessage($request, $demand, $message))->values()->all(),
            'attachments' => $demand->attachments->map(fn (DemandAttachment $attachment) => self::demandAttachment($request, $demand, $attachment))->values()->all(),
            'updated_at' => $demand->updated_at?->toAtomString(),
        ];
    }

    public static function demandMessage(Request $request, Demand $demand, DemandMessage $message): array
    {
        return [
            'id' => (int) $message->id,
            'sender_type' => (string) $message->sender_type,
            'sender_name' => (string) $message->senderName(),
            'message' => (string) $message->message,
            'created_at' => $message->created_at?->toAtomString(),
            'created_at_br' => $message->created_at?->format('d/m/Y H:i'),
            'attachments' => $message->attachments->map(fn (DemandAttachment $attachment) => self::demandAttachment($request, $demand, $attachment))->values()->all(),
        ];
    }

    public static function demandAttachment(Request $request, Demand $demand, DemandAttachment $attachment): array
    {
        return [
            'id' => (int) $attachment->id,
            'original_name' => (string) $attachment->original_name,
            'mime_type' => $attachment->mime_type ? (string) $attachment->mime_type : null,
            'file_size' => (int) $attachment->file_size,
            'download_url' => route('api.mobile.v1.demands.attachments.download', [
                'demand' => $demand->id,
                'attachment' => $attachment->id,
            ]),
        ];
    }

    public static function notification(ClientPortalNotification $notification): array
    {
        return [
            'id' => (int) $notification->id,
            'type' => (string) $notification->type,
            'title' => (string) $notification->title,
            'body' => (string) $notification->body,
            'data' => $notification->data ?? [],
            'read_at' => $notification->read_at?->toAtomString(),
            'created_at' => $notification->created_at?->toAtomString(),
            'created_at_br' => $notification->created_at?->format('d/m/Y H:i'),
        ];
    }

    public static function lemeConversation(?AiChatConversation $conversation, iterable $messages, array $usageStatus, ?ClientCondominium $activeCondominium): array
    {
        return [
            'conversation_id' => $conversation?->id ? (int) $conversation->id : null,
            'active_condominium' => $activeCondominium ? self::condominium($activeCondominium) : null,
            'usage_status' => $usageStatus,
            'messages' => collect($messages)->map(fn (AiChatMessage $message) => self::lemeMessage($message))->values()->all(),
        ];
    }

    public static function lemeMessage(AiChatMessage $message): array
    {
        return [
            'id' => (int) $message->id,
            'role' => (string) $message->role,
            'content' => (string) $message->content,
            'status' => (string) $message->status,
            'created_at' => $message->created_at?->toAtomString(),
            'created_at_br' => $message->created_at?->format('d/m/Y H:i'),
            'documents' => collect((array) data_get($message->meta_json, 'documents', []))->values()->all(),
        ];
    }

    public static function recentMovements(Collection $processPhases, Collection $demands): array
    {
        $processItems = $processPhases->map(function (ProcessCasePhase $phase) {
            return [
                'type' => 'process_phase',
                'title' => $phase->processCase?->process_number ?: ('Processo #' . $phase->process_case_id),
                'description' => $phase->description,
                'date' => $phase->phase_date?->toAtomString() ?: $phase->created_at?->toAtomString(),
            ];
        });

        $demandItems = $demands->map(function (Demand $demand) {
            return [
                'type' => 'demand_update',
                'title' => $demand->protocol,
                'description' => $demand->subject,
                'date' => $demand->updated_at?->toAtomString(),
            ];
        });

        return $processItems
            ->concat($demandItems)
            ->sortByDesc('date')
            ->values()
            ->take(8)
            ->all();
    }

    private static function processClientName(ProcessCase $case): ?string
    {
        return self::cleanProcessPartyName(
            $case->clientCondominium?->name
            ?: $case->client?->display_name
            ?: $case->client_name_snapshot
        );
    }

    private static function processAdverseName(ProcessCase $case): ?string
    {
        return self::cleanProcessPartyName(
            $case->adverseCondominium?->name
            ?: $case->adverse?->display_name
            ?: $case->adverse_name
        );
    }

    private static function processPartiesLabel(?string $clientName, ?string $adverseName): ?string
    {
        $client = $clientName ?: 'Cliente nao informado';
        $adverse = $adverseName ?: 'Adverso nao informado';

        return $client . ' x ' . $adverse;
    }

    private static function cleanProcessPartyName(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
