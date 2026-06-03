<?php

namespace Tests\Unit\Cobranca;

use App\Http\Controllers\Cobranca\CobrancaMonetaryStandaloneController;
use App\Models\ClientEntity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CobrancaStandaloneMonetaryUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_standalone_metadata_from_the_linked_avulso_client(): void
    {
        $client = ClientEntity::query()->create([
            'entity_type' => 'pf',
            'profile_scope' => 'avulso',
            'display_name' => 'Maria da Penha',
            'cpf_cnpj' => '123.456.789-00',
            'emails_json' => [['email' => 'maria@example.com']],
            'phones_json' => [['number' => '(27) 99999-1111']],
            'is_active' => true,
        ]);

        $request = Request::create('/cobrancas/tjes-avulso', 'POST', [
            'client_entity_id' => $client->id,
            'final_date' => '2026-05-31',
            'quotas' => [
                [
                    'selected' => '1',
                    'reference_label' => '05/2026',
                    'due_date' => '2026-05-10',
                    'original_amount' => '1.250,80',
                ],
            ],
        ]);

        $controller = $this->app->make(CobrancaMonetaryStandaloneController::class);
        [$metadata, $errors, $quotaRows, $options] = $this->invokePrivate($controller, 'standaloneMonetaryPayloadFromRequest', [$request, true]);

        $this->assertSame([], $errors);
        $this->assertSame($client->id, $metadata['client_entity_id']);
        $this->assertSame('Maria da Penha', $metadata['debtor_name_snapshot']);
        $this->assertSame('123.456.789-00', $metadata['debtor_document_snapshot']);
        $this->assertSame('maria@example.com', $metadata['debtor_email_snapshot']);
        $this->assertSame('(27) 99999-1111', $metadata['debtor_phone_snapshot']);
        $this->assertSame('05/2026', $quotaRows[0]['reference_label']);
        $this->assertSame('2026-05-10', $quotaRows[0]['due_date']);
        $this->assertSame(1250.80, $quotaRows[0]['original_amount']);
        $this->assertSame('ATM', $options['index_code']);
    }

    public function test_it_rejects_selected_standalone_debts_without_due_date_or_amount(): void
    {
        $request = Request::create('/cobrancas/tjes-avulso', 'POST', [
            'debtor_name_snapshot' => 'Devedor Teste',
            'final_date' => '2026-05-31',
            'quotas' => [
                [
                    'selected' => '1',
                    'reference_label' => '05/2026',
                    'due_date' => '',
                    'original_amount' => '0,00',
                ],
                [
                    'selected' => '1',
                    'reference_label' => '06/2026',
                    'due_date' => '2026-06-10',
                    'original_amount' => '0,00',
                ],
            ],
        ]);

        $controller = $this->app->make(CobrancaMonetaryStandaloneController::class);
        [, $errors, $quotaRows] = $this->invokePrivate($controller, 'standaloneMonetaryPayloadFromRequest', [$request, true]);

        $this->assertSame([], $quotaRows);
        $this->assertContains('Informe o vencimento do debito 1.', $errors);
        $this->assertContains('Informe um valor original maior que zero para o debito 2.', $errors);
    }

    private function invokePrivate(object $instance, string $method, array $arguments = []): mixed
    {
        $reflection = new \ReflectionMethod($instance, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($instance, $arguments);
    }
}
