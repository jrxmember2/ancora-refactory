<?php

namespace Tests\Unit\Cobranca;

use App\Http\Controllers\CobrancaController;
use App\Models\ClientBlock;
use App\Models\ClientCondominium;
use App\Models\ClientEntity;
use App\Models\ClientUnit;
use App\Models\CobrancaCase;
use App\Models\CobrancaCaseQuota;
use App\Models\CobrancaImportBatch;
use App\Models\CobrancaImportRow;
use App\Models\User;
use App\Support\AncoraAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CobrancaImportSafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_matches_a_unit_when_the_block_is_embedded_in_the_unit_code(): void
    {
        $scenario = $this->createScenario(withBlock: true);
        $batch = $this->createBatch($scenario['user']);
        $row = $this->createImportRow($batch, [
            'condominium_input' => $scenario['condominium']->name,
            'block_input' => '',
            'unit_input' => 'A-401',
            'owner_input' => $scenario['owner']->display_name,
            'reference_input' => '03/2026',
            'due_date_input' => '10/03/2026',
            'amount_value' => 1250.80,
        ]);

        $controller = $this->app->make(CobrancaController::class);
        $context = $this->invokePrivate($controller, 'buildImportContext');
        $result = $this->invokePrivate($controller, 'matchImportUnit', [$scenario['condominium'], $row, $context]);

        $this->assertInstanceOf(ClientUnit::class, $result['unit']);
        $this->assertSame($scenario['unit']->id, $result['unit']->id);
    }

    public function test_it_ignores_the_row_when_the_owner_name_diverges(): void
    {
        $scenario = $this->createScenario(withBlock: true);
        $batch = $this->createBatch($scenario['user']);
        $row = $this->createImportRow($batch, [
            'condominium_input' => $scenario['condominium']->name,
            'block_input' => 'Bloco A',
            'unit_input' => '401',
            'owner_input' => 'Pessoa Divergente',
            'reference_input' => '03/2026',
            'due_date_input' => '10/03/2026',
            'amount_value' => 1250.80,
        ]);

        $controller = $this->app->make(CobrancaController::class);
        $context = $this->invokePrivate($controller, 'buildImportContext');
        $this->invokePrivate($controller, 'classifyImportRow', [$row, $context]);

        $row->refresh();

        $this->assertSame('ignored_owner', $row->status);
        $this->assertSame('owner_mismatch', $row->issue_code);
        $this->assertStringContainsString('Atualize o cadastro da unidade', (string) $row->message);
    }

    public function test_it_groups_multiple_rows_from_the_same_unit_into_a_single_case_during_processing(): void
    {
        $scenario = $this->createScenario(withBlock: true);
        $batch = $this->createBatch($scenario['user']);

        $this->createImportRow($batch, [
            'row_number' => 2,
            'condominium_input' => $scenario['condominium']->name,
            'block_input' => 'Bloco A',
            'unit_input' => '401',
            'owner_input' => $scenario['owner']->display_name,
            'reference_input' => '03/2026',
            'due_date_input' => '10/03/2026',
            'amount_value' => 1250.80,
        ]);

        $this->createImportRow($batch, [
            'row_number' => 3,
            'condominium_input' => $scenario['condominium']->name,
            'block_input' => 'Bloco A',
            'unit_input' => '401',
            'owner_input' => $scenario['owner']->display_name,
            'reference_input' => '04/2026',
            'due_date_input' => '10/04/2026',
            'amount_value' => 980.50,
        ]);

        $controller = $this->app->make(CobrancaController::class);
        $request = $this->authenticatedRequest($scenario['user']);

        $this->invokePrivate($controller, 'classifyImportBatchV2', [$batch]);
        $response = $this->invokePrivate($controller, 'importProcessV2', [$request, $batch->fresh()]);

        $batch->refresh();

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(1, CobrancaCase::query()->count());
        $this->assertSame(2, CobrancaCaseQuota::query()->count());
        $this->assertSame('processed', $batch->status);
        $this->assertSame(1, (int) data_get($batch->summary_json, 'processed.created_cases'));
        $this->assertSame(1, (int) data_get($batch->summary_json, 'processed.linked_cases'));
    }

    private function createScenario(bool $withBlock = false): array
    {
        $user = User::query()->create([
            'name' => 'Teste Importacao',
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
            'display_name' => 'Joao Carlos Oliveira',
            'cpf_cnpj' => '390.533.447-05',
            'emails_json' => [['email' => 'joao@example.com']],
            'phones_json' => [['number' => '27999999999']],
            'is_active' => true,
        ]);

        $condominium = ClientCondominium::query()->create([
            'name' => 'Condominio Costa Allegra',
            'syndico_entity_id' => $owner->id,
            'has_blocks' => $withBlock,
            'is_active' => true,
        ]);

        $block = null;
        if ($withBlock) {
            $block = ClientBlock::query()->create([
                'condominium_id' => $condominium->id,
                'name' => 'Bloco A',
                'sort_order' => 1,
            ]);
        }

        $unit = ClientUnit::query()->create([
            'condominium_id' => $condominium->id,
            'block_id' => $block?->id,
            'unit_number' => '401',
            'owner_entity_id' => $owner->id,
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        return compact('user', 'owner', 'condominium', 'block', 'unit');
    }

    private function createBatch(User $user): CobrancaImportBatch
    {
        return CobrancaImportBatch::query()->create([
            'original_name' => 'inadimplencia.xlsx',
            'stored_name' => 'inadimplencia.xlsx',
            'sheet_name' => 'Planilha 1',
            'file_extension' => 'xlsx',
            'status' => 'parsed',
            'uploaded_by' => $user->id,
        ]);
    }

    private function createImportRow(CobrancaImportBatch $batch, array $attributes = []): CobrancaImportRow
    {
        return CobrancaImportRow::query()->create(array_merge([
            'batch_id' => $batch->id,
            'row_number' => 2,
            'raw_payload_json' => [],
            'condominium_input' => '',
            'block_input' => '',
            'unit_input' => '',
            'owner_input' => '',
            'reference_input' => '',
            'due_date_input' => '',
            'amount_value' => null,
            'quota_type_input' => 'taxa_mes',
            'status' => 'error_required',
        ], $attributes));
    }

    private function authenticatedRequest(User $user): Request
    {
        $request = Request::create('/cobrancas/importacao/processar', 'POST');
        $session = app('session')->driver();
        $session->start();
        $request->setLaravelSession($session);
        AncoraAuth::cacheSessionUser($request, $user);

        return $request;
    }

    private function invokePrivate(object $instance, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($instance, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($instance, $arguments);
    }
}
