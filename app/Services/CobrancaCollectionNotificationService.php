<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\CobrancaCase;
use App\Models\CobrancaCaseEmailHistory;
use App\Models\CobrancaCaseTimeline;
use App\Models\User;
use App\Support\AncoraBillingMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CobrancaCollectionNotificationService
{
    public function __construct(
        private readonly EvolutionApiService $evolutionApiService,
        private readonly EvolutionMessageLogService $messageLogService,
        private readonly NotificationTemplateService $templateService,
    ) {
    }

    public function channelStatus(): array
    {
        $settings = $this->evolutionApiService->currentSettings();

        return [
            'email_enabled' => AncoraBillingMail::isSmtpConfigured(),
            'whatsapp_enabled' => $this->evolutionApiService->hasReadyConfiguration($settings),
        ];
    }

    public function recipientCatalog(CobrancaCase $case): array
    {
        $case->loadMissing([
            'contacts',
            'unit.owner',
        ]);

        $emails = [];
        foreach ($case->contacts->where('contact_type', 'email') as $contact) {
            $email = trim((string) $contact->value);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $emails[strtolower($email)] = [
                'value' => $email,
                'label' => (string) ($contact->label ?: 'E-mail da OS'),
                'source' => 'OS',
            ];
        }

        foreach (collect($case->unit?->owner?->emails_json ?? [])->pluck('email')->filter() as $email) {
            $email = trim((string) $email);
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $emails[strtolower($email)] ??= [
                'value' => $email,
                'label' => 'E-mail do proprietario',
                'source' => 'Proprietario',
            ];
        }

        $phones = [];
        foreach ($case->contacts->where('contact_type', 'phone') as $contact) {
            if (!$contact->is_whatsapp) {
                continue;
            }

            $normalized = preg_replace('/\D+/', '', (string) $contact->value) ?: '';
            if (strlen($normalized) < 10) {
                continue;
            }

            $phones[$normalized] = [
                'value' => $normalized,
                'display' => (string) $contact->value,
                'label' => (string) ($contact->label ?: 'WhatsApp da OS'),
                'source' => 'OS',
            ];
        }

        foreach (collect($case->unit?->owner?->phones_json ?? [])->pluck('number')->filter() as $phone) {
            $normalized = preg_replace('/\D+/', '', (string) $phone) ?: '';
            if (strlen($normalized) < 10) {
                continue;
            }

            $phones[$normalized] ??= [
                'value' => $normalized,
                'display' => (string) $phone,
                'label' => 'WhatsApp do proprietario',
                'source' => 'Proprietario',
            ];
        }

        return [
            'emails' => array_values($emails),
            'phones' => array_values($phones),
        ];
    }

    public function preview(CobrancaCase $case): array
    {
        $variables = $this->templateService->collectionVariables($case);
        $subjectTemplate = trim((string) AppSetting::getValue('evolution_template_collection_email_subject', ''));
        $bodyTemplate = trim((string) AppSetting::getValue('evolution_template_collection_email_body', ''));
        $subjectTemplate = $subjectTemplate !== '' ? $subjectTemplate : NotificationTemplateService::defaultCollectionEmailSubject();
        $bodyTemplate = $bodyTemplate !== '' ? $bodyTemplate : NotificationTemplateService::defaultCollectionEmailBody();

        return [
            'subject' => $this->templateService->render($subjectTemplate, $variables),
            'body' => $this->templateService->render($bodyTemplate, $variables),
        ];
    }

    public function canOpenModal(CobrancaCase $case): array
    {
        $state = $this->eligibilityState($case);

        return [
            'available' => $state['available'],
            'reason' => $state['reason'],
            'catalog' => $state['catalog'],
            'channels' => $state['channels'],
        ];
    }

    public function eligibilityState(CobrancaCase $case): array
    {
        $catalog = $this->recipientCatalog($case);
        $channels = $this->channelStatus();

        $hasEmailPath = $channels['email_enabled'] && $catalog['emails'] !== [];
        $hasWhatsappPath = $channels['whatsapp_enabled'] && $catalog['phones'] !== [];

        return [
            'available' => $hasEmailPath || $hasWhatsappPath,
            'can_email' => $hasEmailPath,
            'can_whatsapp' => $hasWhatsappPath,
            'catalog' => $catalog,
            'channels' => $channels,
            'email_count' => count($catalog['emails']),
            'phone_count' => count($catalog['phones']),
            'reason' => $this->eligibilityReason($channels, $catalog, $hasEmailPath, $hasWhatsappPath),
        ];
    }

    public function send(CobrancaCase $case, array $selectedEmails, array $selectedPhones, ?User $user, int $whatsappDelayOffsetMs = 0): array
    {
        $case->loadMissing([
            'condominium',
            'block',
            'unit.owner',
            'debtor',
            'contacts',
            'quotas',
        ]);

        $catalog = $this->recipientCatalog($case);
        $channels = $this->channelStatus();
        $availableEmails = collect($catalog['emails'])->pluck('value')->map(fn ($value) => trim((string) $value))->all();
        $availablePhones = collect($catalog['phones'])->pluck('value')->map(fn ($value) => preg_replace('/\D+/', '', (string) $value) ?: '')->all();

        $emails = collect($selectedEmails)
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn ($value) => $value !== '' && in_array($value, $availableEmails, true))
            ->unique()
            ->values()
            ->all();

        $phones = collect($selectedPhones)
            ->map(fn ($value) => preg_replace('/\D+/', '', (string) $value) ?: '')
            ->filter(fn ($value) => $value !== '' && in_array($value, $availablePhones, true))
            ->unique()
            ->values()
            ->all();

        if ($emails === [] && $phones === []) {
            throw new \RuntimeException('Selecione ao menos um e-mail ou um numero de WhatsApp para notificar.');
        }

        $variables = $this->templateService->collectionVariables($case);
        $settings = $this->evolutionApiService->currentSettings();
        $whatsappTemplate = trim((string) ($settings['evolution_template_collection_notice'] ?? ''));
        if ($whatsappTemplate === '') {
            $whatsappTemplate = NotificationTemplateService::defaultCollectionWhatsappTemplate();
        }

        $emailSubjectTemplate = trim((string) AppSetting::getValue('evolution_template_collection_email_subject', ''));
        $emailBodyTemplate = trim((string) AppSetting::getValue('evolution_template_collection_email_body', ''));
        $emailSubjectTemplate = $emailSubjectTemplate !== '' ? $emailSubjectTemplate : NotificationTemplateService::defaultCollectionEmailSubject();
        $emailBodyTemplate = $emailBodyTemplate !== '' ? $emailBodyTemplate : NotificationTemplateService::defaultCollectionEmailBody();

        $emailSubject = $this->templateService->render($emailSubjectTemplate, $variables);
        $emailBodyText = $this->templateService->render($emailBodyTemplate, $variables);
        $emailBodyHtml = $this->templateService->collectionEmailHtml($emailSubject, $emailBodyText);
        $whatsappMessage = $this->templateService->render($whatsappTemplate, $variables);
        $whatsappDelayStepMs = max(0, (int) ($settings['evolution_message_dispatch_delay_ms'] ?? EvolutionApiService::defaultDispatchDelayMs()));

        $emailResult = null;
        $whatsappResults = [];

        if ($emails !== []) {
            if (!$channels['email_enabled']) {
                throw new \RuntimeException('Configure o SMTP de cobranca antes de enviar a notificacao por e-mail.');
            }

            $emailHistory = CobrancaCaseEmailHistory::query()->create([
                'cobranca_case_id' => $case->id,
                'cobranca_monetary_update_id' => null,
                'sent_by' => $user?->id,
                'from_address' => AncoraBillingMail::smtp()['from_address'] ?? null,
                'from_name' => AncoraBillingMail::smtp()['from_name'] ?? null,
                'subject' => Str::limit($emailSubject, 255, ''),
                'recipients_json' => $emails,
                'body_html' => $emailBodyHtml,
                'send_status' => 'pending',
                'transport_message' => 'Envio de notificacao de inadimplencia iniciado.',
                'imap_status' => 'pending',
                'imap_message' => 'Aguardando envio pelo SMTP de cobranca.',
            ]);

            $emailResult = AncoraBillingMail::sendHtml([
                'subject' => $emailSubject,
                'html' => $emailBodyHtml,
                'to' => $emails,
            ]);

            $emailHistory->update([
                'send_status' => (string) ($emailResult['send_status'] ?? 'failed'),
                'transport_message' => Str::limit((string) ($emailResult['transport_message'] ?? ''), 65535, ''),
                'imap_status' => (string) ($emailResult['imap_status'] ?? 'not_attempted'),
                'imap_message' => Str::limit((string) ($emailResult['imap_message'] ?? ''), 65535, ''),
            ]);
        }

        if ($phones !== []) {
            if (!$channels['whatsapp_enabled']) {
                throw new \RuntimeException('Configure a EvolutionAPI antes de enviar a notificacao por WhatsApp.');
            }

            foreach ($phones as $index => $phone) {
                try {
                    $response = $this->evolutionApiService->sendTextMessage(
                        $settings,
                        $phone,
                        $whatsappMessage,
                        $whatsappDelayOffsetMs + ($whatsappDelayStepMs * $index)
                    );
                    $this->messageLogService->recordOutbound('cobrancas', $phone, $whatsappMessage, $response, [
                        'cobranca_case_id' => $case->id,
                        'metadata' => [
                            'channel' => 'collection_notice',
                            'delay_offset_ms' => $whatsappDelayOffsetMs + ($whatsappDelayStepMs * $index),
                            'sent_by_user_id' => $user?->id,
                        ],
                    ]);
                    $whatsappResults[] = [
                        'value' => $phone,
                        'status' => 'sent',
                        'message_id' => (string) ($response['message_id'] ?? ''),
                    ];
                } catch (\Throwable $e) {
                    $this->messageLogService->recordOutboundFailure('cobrancas', $phone, $whatsappMessage, $e->getMessage(), [
                        'cobranca_case_id' => $case->id,
                        'metadata' => [
                            'channel' => 'collection_notice',
                            'delay_offset_ms' => $whatsappDelayOffsetMs + ($whatsappDelayStepMs * $index),
                            'sent_by_user_id' => $user?->id,
                        ],
                    ]);
                    $whatsappResults[] = [
                        'value' => $phone,
                        'status' => 'failed',
                        'message' => $e->getMessage(),
                    ];
                }
            }
        }

        $emailsSent = $emailResult && (($emailResult['send_status'] ?? 'failed') === 'sent') ? count($emails) : 0;
        $phonesSent = collect($whatsappResults)->where('status', 'sent')->count();
        $phonesFailed = collect($whatsappResults)->where('status', 'failed')->count();
        $totalSent = $emailsSent + $phonesSent;

        DB::transaction(function () use ($case, $user, $emails, $phones, $emailsSent, $phonesSent, $phonesFailed, $totalSent) {
            $parts = [];
            if ($emails !== []) {
                $parts[] = 'E-mail: ' . $emailsSent . '/' . count($emails) . ' enviado(s)';
            }
            if ($phones !== []) {
                $parts[] = 'WhatsApp: ' . $phonesSent . '/' . count($phones) . ' enviado(s)';
            }
            if ($phonesFailed > 0) {
                $parts[] = $phonesFailed . ' falha(s) no WhatsApp';
            }

            CobrancaCaseTimeline::query()->create([
                'cobranca_case_id' => $case->id,
                'event_type' => 'notificacao',
                'description' => ($totalSent > 0
                    ? 'Notificacao de inadimplencia disparada. '
                    : 'Tentativa de notificacao de inadimplencia sem sucesso. ')
                    . implode(' · ', $parts) . '.',
                'user_id' => $user?->id,
                'user_email' => $user?->email,
                'created_at' => now(),
            ]);

            $payload = [
                'last_progress_at' => now(),
                'updated_by' => $user?->id,
            ];

            if ($totalSent > 0 && (string) $case->workflow_stage === 'apto_notificar') {
                $payload['workflow_stage'] = 'notificado';
            }

            $case->updateQuietly($payload);
        });

        return [
            'emails_selected' => count($emails),
            'emails_sent' => $emailsSent,
            'email_result' => $emailResult,
            'phones_selected' => count($phones),
            'phones_sent' => $phonesSent,
            'phone_failures' => collect($whatsappResults)->where('status', 'failed')->values()->all(),
            'next_whatsapp_delay_offset_ms' => $whatsappDelayOffsetMs + ($whatsappDelayStepMs * count($phones)),
        ];
    }

    public function sendToAllRecipients(CobrancaCase $case, bool $sendEmail, bool $sendWhatsapp, ?User $user, int $whatsappDelayOffsetMs = 0): array
    {
        $catalog = $this->recipientCatalog($case);

        return $this->send(
            $case,
            $sendEmail ? collect($catalog['emails'])->pluck('value')->values()->all() : [],
            $sendWhatsapp ? collect($catalog['phones'])->pluck('value')->values()->all() : [],
            $user,
            $whatsappDelayOffsetMs
        );
    }

    private function eligibilityReason(array $channels, array $catalog, bool $hasEmailPath, bool $hasWhatsappPath): ?string
    {
        if ($hasEmailPath || $hasWhatsappPath) {
            return null;
        }

        if (!$channels['email_enabled'] && !$channels['whatsapp_enabled']) {
            return 'Configure o SMTP de cobranca ou a EvolutionAPI antes de notificar.';
        }

        if ($catalog['emails'] === [] && $catalog['phones'] === []) {
            return 'Cadastre e-mail ou WhatsApp do proprietario/OS antes de notificar.';
        }

        if ($channels['email_enabled'] && $catalog['emails'] === [] && !$channels['whatsapp_enabled']) {
            return 'Nao ha e-mails disponiveis para esta OS.';
        }

        if ($channels['whatsapp_enabled'] && $catalog['phones'] === [] && !$channels['email_enabled']) {
            return 'Nao ha WhatsApp disponivel para esta OS.';
        }

        return 'Revise os destinatarios e a configuracao dos canais antes de notificar.';
    }
}
