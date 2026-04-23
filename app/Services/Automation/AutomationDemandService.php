<?php

namespace App\Services\Automation;

use App\Models\AutomationAgreementProposal;
use App\Models\AutomationDebtSnapshot;
use App\Models\AutomationSession;
use App\Models\Demand;
use App\Models\DemandCategory;
use App\Models\DemandMessage;
use App\Models\DemandTag;
use App\Support\Automation\AutomationText;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AutomationDemandService
{
    public function createDemand(
        AutomationSession $session,
        AutomationAgreementProposal $proposal,
        ?AutomationDebtSnapshot $snapshot,
    ): Demand {
        $session->loadMissing(['condominium', 'block', 'unit', 'validatedPerson', 'cobrancaCase']);

        return DB::transaction(function () use ($session, $proposal, $snapshot) {
            $category = $this->resolveCategory();
            $description = $this->buildDescription($session, $proposal, $snapshot);
            $status = (string) config('automation.demand.default_status', 'aguardando_formalizacao_acordo');
            $tag = DemandTag::defaultForStatus($status) ?: DemandTag::default();
            $slaStartedAt = $tag?->sla_hours ? now() : null;
            $demand = Demand::query()->create([
                'protocol' => $this->nextProtocol(),
                'origin' => (string) config('automation.demand.origin', 'automation_whatsapp'),
                'client_entity_id' => $session->validated_person_id,
                'client_condominium_id' => $session->condominium_id,
                'cobranca_case_id' => $session->cobranca_case_id,
                'automation_session_id' => $session->id,
                'automation_agreement_proposal_id' => $proposal->id,
                'category_id' => $category?->id,
                'subject' => $this->buildSubject($session),
                'description' => $description,
                'priority' => (string) config('automation.demand.priority', 'normal'),
                'status' => $tag?->status_key ?: $status,
                'demand_tag_id' => $tag?->id,
                'last_external_message_at' => now(),
                'sla_started_at' => $slaStartedAt,
                'sla_due_at' => $slaStartedAt
                    ? $slaStartedAt->copy()->addHours((int) $tag->sla_hours)
                    : $this->addBusinessHours(now(), (int) config('automation.demand.sla_business_hours', 24)),
            ]);

            DemandMessage::query()->create([
                'demand_id' => $demand->id,
                'sender_type' => 'internal',
                'message' => $description,
                'is_internal' => true,
            ]);

            return $demand;
        });
    }

    private function buildSubject(AutomationSession $session): string
    {
        $parts = array_filter([
            $session->condominium?->name,
            $session->block?->name,
            $session->unit?->unit_number,
        ]);

        return 'Formalizar acordo de unidade ' . implode(' / ', $parts);
    }

    private function buildDescription(
        AutomationSession $session,
        AutomationAgreementProposal $proposal,
        ?AutomationDebtSnapshot $snapshot,
    ): string {
        $debts = collect((array) data_get($snapshot?->snapshot_payload, 'debts', []))
            ->map(fn (array $debt) => ($debt['type'] ?? 'Débito') . ' - competência ' . ($debt['competency'] ?? '-') . ' - vencimento ' . ($debt['due_date'] ?? '-'))
            ->implode("\n");

        $calculationComponents = collect((array) data_get($proposal->calculation_memory, 'components', []))
            ->map(function ($value, $key) {
                $label = match ($key) {
                    'principal' => 'Principal',
                    'tjes_update' => 'Atualização TJES',
                    'interest' => 'Juros',
                    'fine' => 'Multa',
                    'process_costs' => 'Custas processuais',
                    'attorney_fees' => 'Honorários',
                    'boleto_fee_total' => 'Taxa de boleto',
                    'boleto_cancellation_fee_total' => 'Taxa de cancelamento de boleto',
                    'total_final' => 'Total final',
                    default => Str::headline($key),
                };

                return $label . ': R$ ' . number_format((float) $value, 2, ',', '.');
            })
            ->implode("\n");

        return implode("\n", array_filter([
            'Telefone do contato: ' . ($session->phone ?: 'não informado'),
            'Condomínio: ' . ($session->condominium?->name ?: 'não informado'),
            $session->block?->name ? 'Bloco/Torre: ' . $session->block->name : null,
            'Unidade: ' . ($session->unit?->unit_number ?: 'não informada'),
            'Responsável validado: ' . ($session->validatedPerson?->display_name ?: 'não informado'),
            'Interlocutor identificado: ' . ($session->interlocutor_name ?: 'não informado'),
            'Débitos encontrados:' . ($debts !== '' ? "\n" . $debts : "\nNenhum débito listado."),
            'Forma de pagamento: ' . ($proposal->payment_mode === 'cash' ? 'À vista' : 'Parcelado'),
            'Primeiro pagamento pretendido: ' . optional($proposal->first_due_date)->format('d/m/Y'),
            $proposal->installments ? 'Quantidade de parcelas: ' . $proposal->installments : null,
            'Memória resumida do cálculo:' . ($calculationComponents !== '' ? "\n" . $calculationComponents : ''),
            'Data/hora da solicitação: ' . now()->format('d/m/Y H:i:s'),
            'Protocolo da sessão: ' . $session->protocol,
        ]));
    }

    private function resolveCategory(): ?DemandCategory
    {
        $slug = (string) config('automation.demand.category_slug', 'cobranca');
        $category = DemandCategory::query()->where('slug', $slug)->first();

        if ($category) {
            return $category;
        }

        $category = DemandCategory::query()
            ->get()
            ->first(function (DemandCategory $item) {
                return str_contains(AutomationText::normalize($item->name), 'cobranc');
            });

        if ($category) {
            return $category;
        }

        return DemandCategory::query()->create([
            'name' => 'Cobrança',
            'slug' => $slug,
            'is_active' => true,
            'sort_order' => 20,
        ]);
    }

    private function nextProtocol(): string
    {
        $year = now()->year;
        $seq = (int) Demand::query()->whereYear('created_at', $year)->lockForUpdate()->count() + 1;

        return sprintf('DEM-%d-%05d', $year, $seq);
    }

    private function addBusinessHours(Carbon $startsAt, int $hours): Carbon
    {
        $cursor = $startsAt->copy();
        $remaining = max(0, $hours);

        while ($remaining > 0) {
            $cursor->addHour();

            if ($cursor->isWeekend()) {
                continue;
            }

            $remaining--;
        }

        return $cursor;
    }
}
