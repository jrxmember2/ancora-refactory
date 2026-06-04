<?php

namespace Tests\Unit\Financeiro;

use App\Models\FinancialPayable;
use App\Support\Financeiro\FinancialColumns;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Garante que o lançamento de "Contas a Pagar" é sempre persistido — inclusive quando o
 * schema do ambiente ainda não recebeu as colunas opcionais series_group/series_index/
 * series_total (migration 2026_06_02_000100). Esse era o motivo de a conta a pagar "não
 * salvar": o insert carregava chaves de coluna inexistentes e disparava "Unknown column".
 *
 * Constrói apenas a tabela financial_payables para não depender do conjunto completo de
 * migrations, que usa guards específicos de MySQL incompatíveis com o SQLite dos testes.
 */
class FinancialPayableStoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FinancialColumns::flush();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('financial_payables');
        FinancialColumns::flush();
        parent::tearDown();
    }

    private function createPayablesTable(bool $withSeries): void
    {
        Schema::dropIfExists('financial_payables');

        Schema::create('financial_payables', function (Blueprint $table) use ($withSeries) {
            $table->id();
            $table->string('code', 60)->nullable();
            $table->string('title', 220);
            $table->integer('supplier_entity_id')->nullable();
            $table->string('supplier_name_snapshot', 220)->nullable();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('cost_center_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('process_id')->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->date('competence_date')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->string('status', 40)->default('aberto');
            $table->string('payment_method', 60)->nullable();
            $table->string('recurrence', 60)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('responsible_user_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            if ($withSeries) {
                $table->string('series_group', 80)->nullable();
                $table->unsignedInteger('series_index')->nullable();
                $table->unsignedInteger('series_total')->nullable();
            }

            $table->timestamps();
            $table->softDeletes();
        });

        FinancialColumns::flush();
    }

    /**
     * Espelha o payload final montado por FinancialController::storePayableSeries para uma
     * conta a pagar avulsa, simulando o input do formulário.
     *
     * @return array<string, mixed>
     */
    private function simulatedFormPayload(): array
    {
        return [
            'code' => 'PAG-0001',
            'title' => 'Aluguel do escritorio',
            'supplier_entity_id' => null,
            'supplier_name_snapshot' => 'Imobiliaria Central',
            'category_id' => null,
            'cost_center_id' => null,
            'account_id' => null,
            'process_id' => null,
            'amount' => 1500.00,
            'due_date' => '2026-06-10',
            'competence_date' => '2026-06-01',
            'status' => 'aberto',
            'payment_method' => 'boleto',
            'recurrence' => null,
            'notes' => 'Lancamento de teste',
            'responsible_user_id' => null,
            'series_group' => null,
            'series_index' => null,
            'series_total' => null,
            'created_by' => null,
            'updated_by' => null,
        ];
    }

    public function test_payable_is_saved_when_series_columns_are_missing(): void
    {
        $this->createPayablesTable(withSeries: false);

        $payload = FinancialColumns::filter('financial_payables', $this->simulatedFormPayload());

        // As colunas inexistentes são removidas, evitando o erro "Unknown column".
        $this->assertArrayNotHasKey('series_group', $payload);
        $this->assertArrayNotHasKey('series_index', $payload);
        $this->assertArrayNotHasKey('series_total', $payload);

        $payable = FinancialPayable::query()->create($payload)->fresh();

        $this->assertTrue($payable->exists);
        $this->assertSame('Aluguel do escritorio', $payable->title);
        $this->assertSame('1500.00', (string) $payable->amount);
        $this->assertSame('aberto', $payable->status);
        $this->assertDatabaseHas('financial_payables', [
            'code' => 'PAG-0001',
            'supplier_name_snapshot' => 'Imobiliaria Central',
            'status' => 'aberto',
        ]);
    }

    public function test_payable_keeps_series_fields_when_columns_exist(): void
    {
        $this->createPayablesTable(withSeries: true);

        $payload = $this->simulatedFormPayload();
        $payload['series_group'] = 'payable-uuid';
        $payload['series_index'] = 2;
        $payload['series_total'] = 12;

        $filtered = FinancialColumns::filter('financial_payables', $payload);
        $this->assertSame('payable-uuid', $filtered['series_group']);

        $payable = FinancialPayable::query()->create($filtered)->fresh();

        $this->assertSame(2, (int) $payable->series_index);
        $this->assertSame(12, (int) $payable->series_total);
        $this->assertSame('payable-uuid', $payable->series_group);
    }
}
