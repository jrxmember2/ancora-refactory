<?php

namespace Tests\Unit\Automation;

use App\Models\AutomationSession;
use App\Models\ClientCondominium;
use App\Models\ClientEntity;
use App\Models\ClientUnit;
use App\Services\Automation\AutomationValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutomationValidationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_name_and_cpf_challenges_with_five_options_and_limited_attempts(): void
    {
        $service = $this->app->make(AutomationValidationService::class);
        $scenario = $this->createScenario();

        $nameChallenge = $service->createNameChallenge($scenario['session'], $scenario['unit'], $scenario['owner']);
        $cpfChallenge = $service->createCpfChallenge($scenario['session'], $scenario['owner']);

        $this->assertCount(5, $nameChallenge->displayed_options);
        $this->assertCount(5, array_unique($nameChallenge->displayed_options));
        $this->assertSame(3, $nameChallenge->max_attempts);
        $this->assertCount(5, $cpfChallenge->displayed_options);
        $this->assertTrue(collect($cpfChallenge->displayed_options)->every(fn (string $item) => strlen($item) === 5));
    }

    public function test_it_marks_the_challenge_as_failed_after_three_invalid_attempts(): void
    {
        $service = $this->app->make(AutomationValidationService::class);
        $scenario = $this->createScenario();

        $challenge = $service->createNameChallenge($scenario['session'], $scenario['unit'], $scenario['owner']);

        $service->validateSelection($challenge, '9');
        $service->validateSelection($challenge->fresh(), '9');
        $result = $service->validateSelection($challenge->fresh(), '9');

        $this->assertSame('failed', $result['status']);
        $this->assertNotNull($challenge->fresh()->failed_at);
    }

    private function createScenario(): array
    {
        $owner = ClientEntity::query()->create([
            'entity_type' => 'pf',
            'profile_scope' => 'contato',
            'role_tag' => 'proprietario',
            'display_name' => 'Paulo Silva',
            'cpf_cnpj' => '390.533.447-05',
            'is_active' => true,
        ]);

        foreach (range(1, 5) as $index) {
            ClientEntity::query()->create([
                'entity_type' => 'pf',
                'profile_scope' => 'contato',
                'role_tag' => 'proprietario',
                'display_name' => 'Vizinho ' . $index,
                'cpf_cnpj' => sprintf('39053344%d%02d', $index, $index + 10),
                'is_active' => true,
            ]);
        }

        $condominium = ClientCondominium::query()->create([
            'name' => 'Residencial Teste',
            'syndico_entity_id' => $owner->id,
            'is_active' => true,
        ]);

        $unit = ClientUnit::query()->create([
            'condominium_id' => $condominium->id,
            'unit_number' => '101',
            'owner_entity_id' => $owner->id,
        ]);

        $session = AutomationSession::query()->create([
            'protocol' => 'AUT-2026-000001',
            'channel' => 'whatsapp',
            'provider' => 'evolution',
            'phone' => '5511999999999',
            'current_flow' => 'collection',
            'current_step' => 'collection_validate_name',
            'status' => 'active',
            'condominium_id' => $condominium->id,
            'unit_id' => $unit->id,
            'validated_person_id' => $owner->id,
            'started_at' => now(),
            'last_interaction_at' => now(),
            'expires_at' => now()->addMinutes(30),
            'metadata' => [],
        ]);

        return compact('owner', 'condominium', 'unit', 'session');
    }
}
