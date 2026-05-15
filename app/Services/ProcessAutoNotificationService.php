<?php

namespace App\Services;

use App\Models\ClientEntity;
use App\Models\ProcessCase;
use App\Models\ProcessCasePhase;
use Illuminate\Support\Facades\Log;

class ProcessAutoNotificationService
{
    public function __construct(
        private readonly EvolutionApiService $evolutionApiService,
        private readonly NotificationTemplateService $templateService,
    ) {
    }

    public function notifyPhase(ProcessCase $case, ProcessCasePhase $phase): array
    {
        $case->loadMissing([
            'client',
            'clientCondominium.syndic',
        ]);

        if ((bool) $phase->is_private) {
            return $this->skip('private_phase', 'Andamento privado nao gera push automatico para o cliente.');
        }

        if (!(bool) $case->push_automatic) {
            return $this->skip('push_disabled', 'O checkbox de push automatico esta desativado neste processo.');
        }

        $settings = $this->evolutionApiService->currentSettings();
        if (!$this->evolutionApiService->hasReadyConfiguration($settings)) {
            return $this->skip('evolution_unavailable', 'A EvolutionAPI nao esta configurada para envio.');
        }

        $target = $this->resolveTarget($case);
        if (!$target['entity']) {
            return $this->skip(
                (string) ($target['reason'] ?? 'missing_recipient'),
                (string) ($target['message'] ?? 'Nao foi possivel localizar o destinatario do push automatico.')
            );
        }

        if ($target['phone'] === '') {
            return $this->skip(
                (string) ($target['reason'] ?? 'missing_phone'),
                (string) ($target['message'] ?? 'O destinatario do push automatico nao possui telefone cadastrado.')
            );
        }

        try {
            $template = trim((string) ($settings['evolution_template_process_update'] ?? ''));
            if ($template === '') {
                $template = NotificationTemplateService::defaultProcessWhatsappTemplate();
            }

            $message = $this->templateService->render(
                $template,
                $this->templateService->processVariables($case, $phase, $target['entity'])
            );

            $result = $this->evolutionApiService->sendTextMessage($settings, $target['phone'], $message);

            return [
                'status' => 'sent',
                'recipient_name' => (string) ($target['entity']->display_name ?: ''),
                'phone' => $target['phone'],
                'message_id' => (string) ($result['message_id'] ?? ''),
            ];
        } catch (\Throwable $e) {
            Log::warning('Falha ao enviar push automatico do processo.', [
                'process_case_id' => $case->id,
                'process_phase_id' => $phase->id,
                'message' => $e->getMessage(),
            ]);

            return [
                'status' => 'failed',
                'reason' => 'send_failed',
                'message' => $e->getMessage(),
            ];
        }
    }

    private function resolveTarget(ProcessCase $case): array
    {
        if ($case->client_condominium_id) {
            if (!$case->clientCondominium?->syndic) {
                return [
                    'entity' => null,
                    'phone' => '',
                    'reason' => 'missing_syndic',
                    'message' => 'O condominio vinculado nao possui sindico cadastrado para receber o push automatico.',
                ];
            }

            $phone = $this->primaryPhone($case->clientCondominium->syndic);

            return [
                'entity' => $case->clientCondominium->syndic,
                'phone' => $phone,
                'reason' => $phone === '' ? 'missing_syndic_phone' : null,
                'message' => $phone === ''
                    ? 'O sindico do condominio vinculado nao possui telefone cadastrado para receber o push automatico.'
                    : null,
            ];
        }

        $phone = $this->primaryPhone($case->client);

        return [
            'entity' => $case->client,
            'phone' => $phone,
            'reason' => $case->client ? ($phone === '' ? 'missing_client_phone' : null) : 'missing_client',
            'message' => !$case->client
                ? 'O processo nao possui cliente avulso vinculado para receber o push automatico.'
                : ($phone === ''
                    ? 'O cliente avulso vinculado ao processo nao possui telefone cadastrado para receber o push automatico.'
                    : null),
        ];
    }

    private function primaryPhone(?ClientEntity $entity): string
    {
        return (string) collect((array) ($entity?->phones_json ?? []))
            ->map(function ($row) {
                if (is_array($row)) {
                    return preg_replace('/\D+/', '', (string) ($row['number'] ?? '')) ?: '';
                }

                return preg_replace('/\D+/', '', (string) $row) ?: '';
            })
            ->filter(fn ($value) => strlen((string) $value) >= 10)
            ->first();
    }

    private function skip(string $reason, string $message): array
    {
        return [
            'status' => 'skipped',
            'reason' => $reason,
            'message' => $message,
        ];
    }
}
