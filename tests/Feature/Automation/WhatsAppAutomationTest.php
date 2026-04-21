<?php

namespace Tests\Feature\Automation;

use App\Models\AutomationSession;
use App\Models\AutomationSessionMessage;
use App\Models\AutomationValidationChallenge;
use App\Models\ClientBlock;
use App\Models\ClientCondominium;
use App\Models\ClientEntity;
use App\Models\ClientUnit;
use App\Models\CobrancaCase;
use App\Models\CobrancaCaseQuota;
use App\Models\CobrancaMonetaryUpdate;
use App\Models\Demand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WhatsAppAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-21 10:00:00');
        config([
            'automation.internal_api.token' => 'test-token',
            'automation.internal_api.allowed_ips' => [],
            'automation.collection.boleto_fees.enabled' => true,
            'automation.collection.boleto_fees.cancellation_enabled' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_completes_the_collection_flow_and_opens_a_demand_for_a_different_interlocutor(): void
    {
        $scenario = $this->createScenario();
        $phone = '5511999999999';

        $this->sendMessage($phone, 'Oi', 'msg-1');
        $this->sendMessage($phone, '2', 'msg-2');
        $this->sendMessage($phone, $scenario['condominium']->name, 'msg-3');
        $this->sendMessage($phone, '1', 'msg-4');
        $this->sendMessage($phone, $scenario['unit']->unit_number, 'msg-5');

        $nameChallenge = AutomationValidationChallenge::query()->where('type', 'name')->latest('id')->firstOrFail();
        $this->sendMessage($phone, (string) $nameChallenge->correct_option_index, 'msg-6');

        $cpfChallenge = AutomationValidationChallenge::query()->where('type', 'cpf_final')->latest('id')->firstOrFail();
        $this->sendMessage($phone, (string) $cpfChallenge->correct_option_index, 'msg-7');

        $this->sendMessage($phone, '2', 'msg-8');
        $debtsResponse = $this->sendMessage($phone, 'Maria Aparecida', 'msg-9');

        $this->assertSame('collection_offer_agreement', data_get($debtsResponse, 'session.current_step'));
        $this->assertTrue(data_get($debtsResponse, 'data.debts.has_open_debts'));

        $this->sendMessage($phone, '1', 'msg-10');
        $this->sendMessage($phone, '2', 'msg-11');
        $this->sendMessage($phone, '3', 'msg-12');
        $finalResponse = $this->sendMessage($phone, '28/04/2026', 'msg-13');

        $session = AutomationSession::query()->latest('id')->firstOrFail();
        $demand = Demand::query()->firstOrFail();

        $this->assertTrue(data_get($finalResponse, 'action.close_session'));
        $this->assertSame('closed', $session->status);
        $this->assertSame('Maria Aparecida', $session->interlocutor_name);
        $this->assertSame($scenario['owner']->id, $session->validated_person_id);
        $this->assertSame('aguardando_formalizacao_acordo', $demand->status);
        $this->assertSame($session->id, $demand->automation_session_id);
        $this->assertStringContainsString($scenario['owner']->display_name, $demand->description);
        $this->assertStringContainsString('Maria Aparecida', $demand->description);
        $this->assertStringContainsString($session->protocol, $demand->description);
        $this->assertSame('installments', data_get($finalResponse, 'data.proposal.payment_mode'));
        $this->assertSame(3, data_get($finalResponse, 'data.proposal.installments'));
        $this->assertSame(9.0, data_get($finalResponse, 'data.proposal.calculation_memory.components.boleto_fee_total'));
        $this->assertSame(3.0, data_get($finalResponse, 'data.proposal.calculation_memory.components.boleto_cancellation_fee_total'));
    }

    public function test_it_handles_multiple_condominiums_and_block_selection(): void
    {
        $scenario = $this->createScenario(withBlock: true, multipleCondominiums: true);
        $phone = '5511988888888';

        $this->sendMessage($phone, 'Oi', 'multi-1');
        $this->sendMessage($phone, '2', 'multi-2');
        $response = $this->sendMessage($phone, 'Aurora', 'multi-3');

        $session = AutomationSession::query()->latest('id')->firstOrFail();
        $matchIds = collect((array) data_get($session->metadata, 'condominium_match_ids', []))->values();
        $selectedIndex = $matchIds->search($scenario['condominium']->id, true) + 1;

        $this->assertSame('collection_confirm_condominium', data_get($response, 'session.current_step'));

        $this->sendMessage($phone, (string) $selectedIndex, 'multi-4');
        $blockPrompt = $this->sendMessage($phone, 'Torre', 'multi-5');

        $session = AutomationSession::query()->latest('id')->firstOrFail();
        $blockIds = collect((array) data_get($session->metadata, 'block_match_ids', []))->values();
        $blockIndex = $blockIds->search($scenario['block']->id, true) + 1;

        $this->assertSame('collection_choose_block', data_get($blockPrompt, 'session.current_step'));

        $unitPrompt = $this->sendMessage($phone, (string) $blockIndex, 'multi-6');
        $challengePrompt = $this->sendMessage($phone, $scenario['unit']->unit_number, 'multi-7');

        $this->assertSame('collection_choose_unit', data_get($unitPrompt, 'session.current_step'));
        $this->assertSame('collection_validate_name', data_get($challengePrompt, 'session.current_step'));
    }

    public function test_it_enforces_idempotency_by_external_message_id(): void
    {
        $this->createScenario();
        $phone = '5511977777777';

        $first = $this->sendMessage($phone, 'Oi', 'same-id');
        $second = $this->sendMessage($phone, 'Oi', 'same-id');

        $this->assertSame($first, $second);
        $this->assertSame(
            1,
            AutomationSessionMessage::query()
                ->where('provider', 'evolution')
                ->where('external_message_id', 'same-id')
                ->count()
        );
    }

    public function test_it_expires_the_session_after_inactivity_and_starts_a_new_one(): void
    {
        $this->createScenario();
        $phone = '5511966666666';

        $first = $this->sendMessage($phone, 'Oi', 'exp-1');
        $oldSession = AutomationSession::query()->latest('id')->firstOrFail();

        $oldSession->update([
            'last_interaction_at' => now()->subMinutes(31),
            'expires_at' => now()->subMinute(),
        ]);

        $second = $this->sendMessage($phone, 'Nova mensagem', 'exp-2');

        $newSession = AutomationSession::query()->latest('id')->firstOrFail();

        $this->assertNotSame(data_get($first, 'session.protocol'), data_get($second, 'session.protocol'));
        $this->assertSame('expired', $oldSession->fresh()->status);
        $this->assertSame('menu', $newSession->current_step);
    }

    public function test_it_hands_over_to_a_human_after_three_invalid_validation_attempts(): void
    {
        $scenario = $this->createScenario();
        $phone = '5511955555555';

        $this->sendMessage($phone, 'Oi', 'fail-1');
        $this->sendMessage($phone, '2', 'fail-2');
        $this->sendMessage($phone, $scenario['condominium']->name, 'fail-3');
        $this->sendMessage($phone, '1', 'fail-4');
        $this->sendMessage($phone, $scenario['unit']->unit_number, 'fail-5');

        $this->sendMessage($phone, '9', 'fail-6');
        $this->sendMessage($phone, '9', 'fail-7');
        $final = $this->sendMessage($phone, '9', 'fail-8');

        $session = AutomationSession::query()->latest('id')->firstOrFail();

        $this->assertTrue(data_get($final, 'action.human_handover'));
        $this->assertTrue(data_get($final, 'action.close_session'));
        $this->assertSame('handover_human', $session->status);
    }

    private function createScenario(
        bool $withBlock = false,
        bool $multipleCondominiums = false,
        string $chargeType = 'extrajudicial',
        bool $withJudicialCosts = false,
    ): array {
        $user = User::query()->create([
            'name' => 'Teste',
            'email' => uniqid('user', true) . '@example.com',
            'password_hash' => 'secret',
            'role' => 'superadmin',
            'theme_preference' => 'dark',
            'is_active' => true,
            'is_protected' => false,
        ]);

        $owner = ClientEntity::query()->create([
            'entity_type' => 'pf',
            'profile_scope' => 'contato',
            'role_tag' => 'proprietario',
            'display_name' => 'Paulo Silva',
            'cpf_cnpj' => '390.533.447-05',
            'phones_json' => [['label' => 'Principal', 'number' => '(11) 99999-9999']],
            'emails_json' => [['label' => 'Principal', 'email' => 'paulo@example.com']],
            'is_active' => true,
        ]);

        foreach (range(1, 6) as $index) {
            ClientEntity::query()->create([
                'entity_type' => 'pf',
                'profile_scope' => 'contato',
                'role_tag' => 'proprietario',
                'display_name' => 'Decoy ' . $index,
                'cpf_cnpj' => sprintf('390.533.44%d-%02d', $index, $index + 10),
                'is_active' => true,
            ]);
        }

        if ($multipleCondominiums) {
            ClientCondominium::query()->create([
                'name' => 'Aurora Prime',
                'syndico_entity_id' => $owner->id,
                'is_active' => true,
            ]);
        }

        $condominium = ClientCondominium::query()->create([
            'name' => $multipleCondominiums ? 'Aurora Residence' : 'Parque das Flores',
            'has_blocks' => $withBlock,
            'syndico_entity_id' => $owner->id,
            'boleto_fee_amount' => 4.50,
            'boleto_cancellation_fee_amount' => 1.50,
            'is_active' => true,
        ]);

        $block = null;
        if ($withBlock) {
            ClientBlock::query()->create(['condominium_id' => $condominium->id, 'name' => 'Torre B', 'sort_order' => 2]);
            $block = ClientBlock::query()->create(['condominium_id' => $condominium->id, 'name' => 'Torre A', 'sort_order' => 1]);
        }

        $unit = ClientUnit::query()->create([
            'condominium_id' => $condominium->id,
            'block_id' => $block?->id,
            'unit_number' => '101',
            'owner_entity_id' => $owner->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $case = CobrancaCase::query()->create([
            'charge_year' => 2026,
            'charge_seq' => random_int(1, 9999),
            'os_number' => 'COB-2026-' . random_int(10000, 99999),
            'condominium_id' => $condominium->id,
            'block_id' => $block?->id,
            'unit_id' => $unit->id,
            'debtor_entity_id' => $owner->id,
            'debtor_role' => 'owner',
            'debtor_name_snapshot' => $owner->display_name,
            'debtor_document_snapshot' => $owner->cpf_cnpj,
            'charge_type' => $chargeType,
            'billing_status' => 'a_faturar',
            'situation' => 'processo_aberto',
            'workflow_stage' => 'em_negociacao',
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'last_progress_at' => now(),
        ]);

        CobrancaCaseQuota::query()->create([
            'cobranca_case_id' => $case->id,
            'reference_label' => '03/2026',
            'due_date' => '2026-03-10',
            'original_amount' => 100.00,
            'status' => 'taxa_mes',
            'created_at' => now(),
        ]);

        CobrancaCaseQuota::query()->create([
            'cobranca_case_id' => $case->id,
            'reference_label' => '04/2026',
            'due_date' => '2026-04-10',
            'original_amount' => 200.00,
            'status' => 'taxa_extra',
            'created_at' => now(),
        ]);

        if ($withJudicialCosts) {
            CobrancaMonetaryUpdate::query()->create([
                'cobranca_case_id' => $case->id,
                'index_code' => 'ATM',
                'calculation_date' => '2026-04-21',
                'final_date' => '2026-04-21',
                'interest_type' => 'legal',
                'fine_percent' => 2,
                'attorney_fee_type' => 'percent',
                'attorney_fee_value' => 20,
                'costs_amount' => 50,
                'costs_date' => '2026-04-01',
                'costs_corrected_amount' => 50,
                'boleto_fee_total' => 0,
                'boleto_cancellation_fee_total' => 0,
                'original_total' => 300,
                'corrected_total' => 300,
                'interest_total' => 0,
                'fine_total' => 0,
                'debit_total' => 350,
                'attorney_fee_amount' => 70,
                'grand_total' => 420,
                'payload_json' => [],
                'generated_by' => $user->id,
            ]);
        }

        return compact('user', 'owner', 'condominium', 'block', 'unit', 'case');
    }

    private function sendMessage(string $phone, string $message, string $externalMessageId): array
    {
        $response = $this->withHeaders(['X-Integration-Token' => 'test-token'])
            ->postJson('/api/internal/automation/whatsapp/process-message', [
                'channel' => 'whatsapp',
                'provider' => 'evolution',
                'phone' => $phone,
                'external_message_id' => $externalMessageId,
                'message_text' => $message,
                'timestamp' => now()->toIso8601String(),
                'metadata' => [],
            ]);

        $response->assertOk();

        return $response->json();
    }
}
