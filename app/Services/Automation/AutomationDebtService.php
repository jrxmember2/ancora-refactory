<?php

namespace App\Services\Automation;

use App\Models\AutomationDebtSnapshot;
use App\Models\AutomationSession;
use App\Models\ClientUnit;
use App\Models\CobrancaCase;

class AutomationDebtService
{
    public function findOpenCaseForUnit(ClientUnit $unit): ?CobrancaCase
    {
        return CobrancaCase::query()
            ->with(['quotas', 'monetaryUpdates'])
            ->where('unit_id', $unit->id)
            ->whereNotIn('situation', ['cancelado', 'pago_encerrado'])
            ->orderByDesc('id')
            ->first();
    }

    public function captureSnapshot(AutomationSession $session, ClientUnit $unit): AutomationDebtSnapshot
    {
        $case = $this->findOpenCaseForUnit($unit);
        $quotas = $case?->quotas ?? collect();
        $latestAppliedUpdate = $case?->monetaryUpdates?->firstWhere('applied_to_case', true) ?: $case?->monetaryUpdates?->first();

        $baseTotal = (float) $quotas->sum('original_amount');
        $updatedTotal = $latestAppliedUpdate
            ? (float) $latestAppliedUpdate->grand_total
            : (float) $quotas->sum(fn ($quota) => $quota->updated_amount ?: $quota->original_amount) + (float) ($case?->fees_amount ?? 0);

        return AutomationDebtSnapshot::query()->create([
            'session_id' => $session->id,
            'unit_id' => $unit->id,
            'cobranca_case_id' => $case?->id,
            'snapshot_payload' => [
                'has_open_debts' => $case !== null && $quotas->isNotEmpty(),
                'charge_type' => $case?->charge_type,
                'debts' => $quotas->map(fn ($quota) => [
                    'type' => $this->quotaTypeLabel((string) $quota->status),
                    'competency' => $quota->reference_label ?: optional($quota->due_date)->format('m/Y'),
                    'due_date' => optional($quota->due_date)->format('d/m/Y'),
                ])->values()->all(),
            ],
            'base_total' => round($baseTotal, 2),
            'updated_total' => round($updatedTotal, 2),
            'calculation_memory' => $latestAppliedUpdate?->payload_json,
            'created_at' => now(),
        ]);
    }

    public function hasOpenDebts(AutomationDebtSnapshot $snapshot): bool
    {
        return (bool) data_get($snapshot->snapshot_payload, 'has_open_debts', false);
    }

    public function presentableDebts(AutomationDebtSnapshot $snapshot): array
    {
        return (array) data_get($snapshot->snapshot_payload, 'debts', []);
    }

    public function renderDebtMessage(string $interlocutorName, AutomationDebtSnapshot $snapshot): string
    {
        $debts = $this->presentableDebts($snapshot);
        if ($debts === []) {
            return 'Olá, ' . $interlocutorName . '. No momento não localizamos débitos em aberto para essa unidade.';
        }

        $lines = ['Olá, ' . $interlocutorName . '.', 'Localizei os seguintes débitos em aberto:'];
        foreach ($debts as $debt) {
            $lines[] = '- ' . ($debt['type'] ?? 'Débito') . ' | competência ' . ($debt['competency'] ?? 'não informada') . ' | vencimento ' . ($debt['due_date'] ?? 'não informado');
        }
        $lines[] = 'Deseja seguir com uma proposta de acordo?';

        return implode("\n", $lines);
    }

    private function quotaTypeLabel(string $status): string
    {
        return match ($status) {
            'taxa_mes' => 'Taxa mensal',
            'taxa_extra' => 'Taxa extra',
            'parcela_acordo' => 'Acordo anterior',
            default => ucwords(str_replace('_', ' ', $status)),
        };
    }
}
