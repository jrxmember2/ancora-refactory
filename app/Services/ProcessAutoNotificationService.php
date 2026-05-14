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

        if (!(bool) $case->push_automatic) {
            return ['status' => 'skipped', 'reason' => 'push_disabled'];
        }

        $settings = $this->evolutionApiService->currentSettings();
        if (!$this->evolutionApiService->hasReadyConfiguration($settings)) {
            return ['status' => 'skipped', 'reason' => 'evolution_unavailable'];
        }

        $target = $this->resolveTarget($case);
        if (!$target['entity'] || $target['phone'] === '') {
            return ['status' => 'skipped', 'reason' => 'missing_phone'];
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
        if ($case->clientCondominium?->syndic) {
            return [
                'entity' => $case->clientCondominium->syndic,
                'phone' => $this->primaryPhone($case->clientCondominium->syndic),
            ];
        }

        return [
            'entity' => $case->client,
            'phone' => $this->primaryPhone($case->client),
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
}
