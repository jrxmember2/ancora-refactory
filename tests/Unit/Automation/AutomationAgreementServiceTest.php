<?php

namespace Tests\Unit\Automation;

use App\Models\AutomationSession;
use App\Models\ClientCondominium;
use App\Models\ClientEntity;
use App\Models\ClientUnit;
use App\Models\CobrancaCase;
use App\Models\CobrancaCaseQuota;
use App\Models\CobrancaMonetaryUpdate;
use App\Models\User;
use App\Services\Automation\AutomationAgreementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AutomationAgreementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-04-21 10:00:00');
        config([
            'automation.collection.boleto_fees.enabled' => true,
            'automation.collection.boleto_fees.cancellation_enabled' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_maximum_first_due_date_uses_the_lower_limit(): void
    {
        $service = $this->app->make(AutomationAgreementService::class);

        $limit = $service->maximumFirstDueDate();

        $this->assertSame('2026-04-28', $limit->toDateString());
        $this->assertNull($service->validateFirstDueDate(Carbon::parse('2026-04-28')));
        $this->assertNotNull($service->validateFirstDueDate(Carbon::parse('2026-04-29')));
    }

    public function test_create_proposal_for_judicial_case_includes_process_costs_when_present(): void
    {
        $scenario = $this->createScenario(withCosts: true);
        $service = $this->app->make(AutomationAgreementService::class);

        $proposal = $service->createProposal($scenario['session'], 'installments', 4, Carbon::parse('2026-04-28'));

        $this->assertSame(4, $proposal->installments);
        $this->assertSame(50.0, data_get($proposal->calculation_memory, 'components.process_costs'));
        $this->assertSame(9.0, data_get($proposal->calculation_memory, 'components.boleto_fee_total'));
        $this->assertSame(3.0, data_get($proposal->calculation_memory, 'components.boleto_cancellation_fee_total'));
        $this->assertGreaterThan(0, data_get($proposal->calculation_memory, 'components.attorney_fees'));
    }

    public function test_create_proposal_for_judicial_case_keeps_process_costs_zero_when_absent(): void
    {
        $scenario = $this->createScenario(withCosts: false);
        $service = $this->app->make(AutomationAgreementService::class);

        $proposal = $service->createProposal($scenario['session'], 'cash', null, Carbon::parse('2026-04-28'));

        $this->assertSame('cash', $proposal->payment_mode);
        $this->assertSame(0.0, data_get($proposal->calculation_memory, 'components.process_costs'));
    }

    private function createScenario(bool $withCosts): array
    {
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
            'is_active' => true,
        ]);

        $condominium = ClientCondominium::query()->create([
            'name' => 'Condominio Judicial',
            'syndico_entity_id' => $owner->id,
            'boleto_fee_amount' => 4.50,
            'boleto_cancellation_fee_amount' => 1.50,
            'is_active' => true,
        ]);

        $unit = ClientUnit::query()->create([
            'condominium_id' => $condominium->id,
            'unit_number' => '301',
            'owner_entity_id' => $owner->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $case = CobrancaCase::query()->create([
            'charge_year' => 2026,
            'charge_seq' => 1,
            'os_number' => 'COB-2026-90001',
            'condominium_id' => $condominium->id,
            'unit_id' => $unit->id,
            'debtor_entity_id' => $owner->id,
            'debtor_role' => 'owner',
            'debtor_name_snapshot' => $owner->display_name,
            'charge_type' => 'judicial',
            'billing_status' => 'a_faturar',
            'situation' => 'processo_aberto',
            'workflow_stage' => 'em_negociacao',
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'last_progress_at' => now(),
        ]);

        CobrancaCaseQuota::query()->create([
            'cobranca_case_id' => $case->id,
            'reference_label' => '01/2026',
            'due_date' => '2026-01-10',
            'original_amount' => 100,
            'status' => 'taxa_mes',
            'created_at' => now(),
        ]);

        CobrancaCaseQuota::query()->create([
            'cobranca_case_id' => $case->id,
            'reference_label' => '02/2026',
            'due_date' => '2026-02-10',
            'original_amount' => 200,
            'status' => 'taxa_extra',
            'created_at' => now(),
        ]);

        if ($withCosts) {
            CobrancaMonetaryUpdate::query()->create([
                'cobranca_case_id' => $case->id,
                'index_code' => 'ATM',
                'calculation_date' => '2026-04-01',
                'final_date' => '2026-04-01',
                'interest_type' => 'legal',
                'fine_percent' => 2,
                'attorney_fee_type' => 'percent',
                'attorney_fee_value' => 20,
                'costs_amount' => 50,
                'costs_date' => '2026-03-15',
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

        $session = AutomationSession::query()->create([
            'protocol' => 'AUT-2026-000001',
            'channel' => 'whatsapp',
            'provider' => 'evolution',
            'phone' => '5511999999999',
            'current_flow' => 'collection',
            'current_step' => 'collection_choose_first_due_date',
            'status' => 'active',
            'condominium_id' => $condominium->id,
            'unit_id' => $unit->id,
            'cobranca_case_id' => $case->id,
            'validated_person_id' => $owner->id,
            'started_at' => now(),
            'last_interaction_at' => now(),
            'expires_at' => now()->addMinutes(30),
            'metadata' => [],
        ]);

        return compact('session');
    }
}
