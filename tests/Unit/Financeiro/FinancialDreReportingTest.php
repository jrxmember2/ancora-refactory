<?php

namespace Tests\Unit\Financeiro;

use App\Models\FinancialCategory;
use App\Models\FinancialTransaction;
use App\Services\FinancialReportingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

/**
 * Teste de unidade isolado do calculo do DRE.
 *
 * Cria apenas as tabelas que o FinancialReportingService::dreData consome
 * (financial_categories + financial_transactions) para nao depender do conjunto
 * completo de migrations, que usa guards especificos de MySQL (information_schema)
 * incompativeis com o SQLite em memoria usado nos testes.
 */
class FinancialDreReportingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('financial_categories', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('receita');
            $table->string('name', 180);
            $table->string('dre_group', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type', 40)->default('entrada');
            $table->unsignedBigInteger('category_id')->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->dateTime('transaction_date')->nullable();
            $table->timestamps();
        });

        Schema::create('financial_receivables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->decimal('final_amount', 14, 2)->default(0);
            $table->date('competence_date')->nullable();
            $table->string('status', 40)->default('aberto');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('financial_payables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->decimal('amount', 14, 2)->default(0);
            $table->date('competence_date')->nullable();
            $table->string('status', 40)->default('aberto');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('financial_payables');
        Schema::dropIfExists('financial_receivables');
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('financial_categories');

        parent::tearDown();
    }

    private function makeCategory(string $name, string $type, ?string $dreGroup): FinancialCategory
    {
        return FinancialCategory::query()->create([
            'type' => $type,
            'name' => $name,
            'dre_group' => $dreGroup,
            'is_active' => true,
        ]);
    }

    private function makeTransaction(string $type, float $amount, ?FinancialCategory $category, string $date = '2026-03-15'): void
    {
        FinancialTransaction::query()->create([
            'transaction_type' => $type,
            'category_id' => $category?->id,
            'amount' => $amount,
            'transaction_date' => $date . ' 10:00:00',
        ]);
    }

    private function dre(string $basis = 'caixa'): array
    {
        return app(FinancialReportingService::class)->dreData(
            Carbon::parse('2026-01-01')->startOfDay(),
            Carbon::parse('2026-12-31')->endOfDay(),
            $basis
        );
    }

    private function makeReceivable(float $finalAmount, ?FinancialCategory $category, string $status = 'aberto', string $competence = '2026-03-01'): void
    {
        \App\Models\FinancialReceivable::query()->create([
            'category_id' => $category?->id,
            'final_amount' => $finalAmount,
            'competence_date' => $competence,
            'status' => $status,
        ]);
    }

    private function makePayable(float $amount, ?FinancialCategory $category, string $status = 'aberto', string $competence = '2026-03-01'): void
    {
        \App\Models\FinancialPayable::query()->create([
            'category_id' => $category?->id,
            'amount' => $amount,
            'competence_date' => $competence,
            'status' => $status,
        ]);
    }

    public function test_it_groups_income_and_expenses_by_dre_group(): void
    {
        $receita = $this->makeCategory('Honorarios teste', 'receita', 'receita_bruta');
        $custo = $this->makeCategory('Custas teste', 'despesa', 'custos');

        $this->makeTransaction('entrada', 1000.00, $receita);
        $this->makeTransaction('saida', 300.00, $custo);

        $data = $this->dre();

        $this->assertSame(1000.00, $data['summary']['receita_bruta']);
        $this->assertSame(1000.00, $data['summary']['receita_liquida']);
        $this->assertSame(300.00, $data['summary']['custos']);
        // Resultado = receita liquida - custos - despesas = 1000 - 300 = 700
        $this->assertSame(700.00, $data['summary']['resultado']);
    }

    public function test_internal_transfers_and_adjustments_do_not_affect_the_dre(): void
    {
        $receita = $this->makeCategory('Honorarios teste', 'receita', 'receita_bruta');
        $this->makeTransaction('entrada', 1000.00, $receita);

        // Transferencia entre contas proprias e ajuste NAO sao receita/despesa do resultado.
        $this->makeTransaction('transferencia', 500.00, null);
        $this->makeTransaction('ajuste', 250.00, null);

        $data = $this->dre();

        $this->assertSame(1000.00, $data['summary']['receita_bruta']);
        // Sem o fix, transferencia/ajuste entrariam como despesa e o resultado cairia para 250.00.
        $this->assertSame(1000.00, $data['summary']['resultado']);
    }

    public function test_transactions_with_unknown_dre_group_are_not_silently_dropped(): void
    {
        // Categoria com grupo DRE invalido (cenario de import/dados legados).
        $receitaInvalida = $this->makeCategory('Receita legada', 'receita', 'receita');
        $this->makeTransaction('entrada', 800.00, $receitaInvalida);

        $data = $this->dre();

        // O valor deve cair no grupo padrao de receita, nunca desaparecer do resultado.
        $this->assertSame(800.00, $data['summary']['receita_bruta']);
        $this->assertSame(800.00, $data['summary']['resultado']);
    }

    public function test_competence_basis_uses_receivables_and_payables_regardless_of_payment(): void
    {
        $receita = $this->makeCategory('Honorarios teste', 'receita', 'receita_bruta');
        $despesa = $this->makeCategory('Custas teste', 'despesa', 'custos');

        // A receber/pagar ainda em aberto (nao recebido/pago) contam no regime de competencia.
        $this->makeReceivable(2000.00, $receita, 'aberto');
        $this->makePayable(500.00, $despesa, 'aberto');
        // Cancelado nao entra.
        $this->makeReceivable(999.00, $receita, 'cancelado');
        // Movimentacao de caixa nao deve influenciar o regime de competencia.
        $this->makeTransaction('entrada', 123.00, $receita);

        $data = $this->dre('competencia');

        $this->assertSame('competencia', $data['basis']);
        $this->assertSame(2000.00, $data['summary']['receita_bruta']);
        $this->assertSame(500.00, $data['summary']['custos']);
        $this->assertSame(1500.00, $data['summary']['resultado']);
    }

    public function test_cash_basis_ignores_open_receivables_and_payables(): void
    {
        $receita = $this->makeCategory('Honorarios teste', 'receita', 'receita_bruta');

        // Conta a receber em aberto NAO entra no regime de caixa (nada foi recebido).
        $this->makeReceivable(2000.00, $receita, 'aberto');
        $this->makeTransaction('entrada', 700.00, $receita);

        $data = $this->dre('caixa');

        $this->assertSame('caixa', $data['basis']);
        $this->assertSame(700.00, $data['summary']['receita_bruta']);
        $this->assertSame(700.00, $data['summary']['resultado']);
    }
}
