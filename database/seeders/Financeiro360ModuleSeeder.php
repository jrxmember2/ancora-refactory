<?php

namespace Database\Seeders;

use App\Support\Financeiro\FinancialCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Financeiro360ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedModule();
        $this->seedRoutePermissions();
        $this->seedSettings();
        $this->seedCategories();
        $this->seedCostCenters();
        $this->seedAccounts();
    }

    private function seedModule(): void
    {
        if (!Schema::hasTable('system_modules')) {
            return;
        }

        DB::table('system_modules')->updateOrInsert(
            ['slug' => 'financeiro'],
            [
                'name' => 'Financeiro 360',
                'icon_class' => 'fa-solid fa-chart-pie',
                'route_prefix' => '/financeiro',
                'is_enabled' => true,
                'sort_order' => 38,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    private function seedRoutePermissions(): void
    {
        if (!Schema::hasTable('route_permissions')) {
            return;
        }

        foreach ($this->routePermissions() as $routeName => $label) {
            $payload = [
                'group_key' => 'financeiro',
                'label' => $label,
            ];

            if (Schema::hasColumn('route_permissions', 'created_at')) {
                $payload['created_at'] = now();
            }
            if (Schema::hasColumn('route_permissions', 'updated_at')) {
                $payload['updated_at'] = now();
            }

            DB::table('route_permissions')->updateOrInsert(['route_name' => $routeName], $payload);
        }
    }

    private function seedSettings(): void
    {
        if (!Schema::hasTable('financial_settings')) {
            return;
        }

        foreach (FinancialCatalog::settings() as $key => $value) {
            DB::table('financial_settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function seedCategories(): void
    {
        if (!Schema::hasTable('financial_categories')) {
            return;
        }

        foreach (FinancialCatalog::defaultCategories() as $index => $category) {
            DB::table('financial_categories')->updateOrInsert(
                ['name' => $category['name']],
                [
                    'type' => $category['type'],
                    'description' => $category['description'],
                    'dre_group' => $category['dre_group'],
                    'color_hex' => $category['color_hex'],
                    'is_active' => true,
                    'sort_order' => ($index + 1) * 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function seedCostCenters(): void
    {
        if (!Schema::hasTable('financial_cost_centers')) {
            return;
        }

        foreach (FinancialCatalog::defaultCostCenters() as $index => $center) {
            DB::table('financial_cost_centers')->updateOrInsert(
                ['name' => $center['name']],
                [
                    'description' => $center['description'],
                    'is_active' => true,
                    'sort_order' => ($index + 1) * 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function seedAccounts(): void
    {
        if (!Schema::hasTable('financial_accounts')) {
            return;
        }

        foreach (FinancialCatalog::defaultAccounts() as $index => $account) {
            DB::table('financial_accounts')->updateOrInsert(
                ['name' => $account['name']],
                [
                    'bank_name' => $account['bank_name'],
                    'account_type' => $account['account_type'],
                    'agency' => $account['agency'],
                    'account_number' => $account['account_number'],
                    'pix_key' => $account['pix_key'],
                    'opening_balance' => $account['opening_balance'],
                    'credit_limit' => $account['credit_limit'],
                    'is_primary' => $account['is_primary'],
                    'is_active' => $account['is_active'],
                    'code' => 'CTA-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    private function routePermissions(): array
    {
        return [
            'financeiro.dashboard' => 'Acessar dashboard financeiro',
            'financeiro.cash-flow.index' => 'Acessar fluxo de caixa',
            'financeiro.cash-flow.store' => 'Cadastrar movimentacao no fluxo de caixa',
            'financeiro.receivables.index' => 'Listar contas a receber',
            'financeiro.receivables.create' => 'Nova conta a receber',
            'financeiro.receivables.store' => 'Salvar conta a receber',
            'financeiro.receivables.show' => 'Visualizar conta a receber',
            'financeiro.receivables.edit' => 'Editar conta a receber',
            'financeiro.receivables.update' => 'Atualizar conta a receber',
            'financeiro.receivables.delete' => 'Excluir conta a receber',
            'financeiro.receivables.duplicate' => 'Duplicar conta a receber',
            'financeiro.receivables.settle' => 'Dar baixa em conta a receber',
            'financeiro.receivables.parcel' => 'Parcelar conta a receber',
            'financeiro.receivables.renegotiate' => 'Renegociar conta a receber',
            'financeiro.receivables.receipt' => 'Gerar recibo de conta a receber',
            'financeiro.receivables.pdf' => 'Gerar PDF de conta a receber',
            'financeiro.receivables.attachments.upload' => 'Enviar anexo de conta a receber',
            'financeiro.receivables.attachments.download' => 'Baixar anexo de conta a receber',
            'financeiro.receivables.attachments.delete' => 'Excluir anexo de conta a receber',
            'financeiro.payables.index' => 'Listar contas a pagar',
            'financeiro.payables.create' => 'Nova conta a pagar',
            'financeiro.payables.store' => 'Salvar conta a pagar',
            'financeiro.payables.show' => 'Visualizar conta a pagar',
            'financeiro.payables.edit' => 'Editar conta a pagar',
            'financeiro.payables.update' => 'Atualizar conta a pagar',
            'financeiro.payables.delete' => 'Excluir conta a pagar',
            'financeiro.payables.duplicate' => 'Duplicar conta a pagar',
            'financeiro.payables.settle' => 'Dar baixa em conta a pagar',
            'financeiro.payables.attachments.upload' => 'Enviar anexo de conta a pagar',
            'financeiro.payables.attachments.download' => 'Baixar anexo de conta a pagar',
            'financeiro.payables.attachments.delete' => 'Excluir anexo de conta a pagar',
            'financeiro.billing.index' => 'Acessar faturamento',
            'financeiro.billing.generate-contract' => 'Gerar cobrancas a partir de contrato',
            'financeiro.accounts.index' => 'Listar bancos e contas',
            'financeiro.accounts.create' => 'Nova conta financeira',
            'financeiro.accounts.store' => 'Salvar conta financeira',
            'financeiro.accounts.edit' => 'Editar conta financeira',
            'financeiro.accounts.update' => 'Atualizar conta financeira',
            'financeiro.accounts.delete' => 'Excluir conta financeira',
            'financeiro.reconciliation.index' => 'Acessar conciliacao bancaria',
            'financeiro.reconciliation.upload' => 'Importar extrato bancario',
            'financeiro.reconciliation.conciliate' => 'Conciliar lancamento bancario',
            'financeiro.collection.index' => 'Acessar cobrancas financeiras',
            'financeiro.delinquency.index' => 'Acessar inadimplencia',
            'financeiro.cost-centers.index' => 'Listar centros de custo',
            'financeiro.cost-centers.store' => 'Salvar centro de custo',
            'financeiro.cost-centers.update' => 'Atualizar centro de custo',
            'financeiro.cost-centers.delete' => 'Excluir centro de custo',
            'financeiro.categories.index' => 'Listar categorias financeiras',
            'financeiro.categories.store' => 'Salvar categoria financeira',
            'financeiro.categories.update' => 'Atualizar categoria financeira',
            'financeiro.categories.delete' => 'Excluir categoria financeira',
            'financeiro.installments.index' => 'Listar parcelamentos',
            'financeiro.installments.show' => 'Visualizar parcelamento',
            'financeiro.installments.store' => 'Salvar parcelamento',
            'financeiro.installments.delete' => 'Excluir parcelamento',
            'financeiro.reimbursements.index' => 'Listar reembolsos',
            'financeiro.reimbursements.create' => 'Novo reembolso',
            'financeiro.reimbursements.store' => 'Salvar reembolso',
            'financeiro.reimbursements.edit' => 'Editar reembolso',
            'financeiro.reimbursements.update' => 'Atualizar reembolso',
            'financeiro.reimbursements.delete' => 'Excluir reembolso',
            'financeiro.process-costs.index' => 'Listar custas processuais',
            'financeiro.process-costs.create' => 'Nova custa processual',
            'financeiro.process-costs.store' => 'Salvar custa processual',
            'financeiro.process-costs.edit' => 'Editar custa processual',
            'financeiro.process-costs.update' => 'Atualizar custa processual',
            'financeiro.process-costs.delete' => 'Excluir custa processual',
            'financeiro.accountability.index' => 'Acessar prestacao de contas',
            'financeiro.accountability.pdf' => 'Gerar PDF de prestacao de contas',
            'financeiro.dre.index' => 'Acessar DRE',
            'financeiro.dre.pdf' => 'Gerar PDF da DRE',
            'financeiro.reports.index' => 'Acessar relatorios financeiros',
            'financeiro.export' => 'Exportar relatorios financeiros',
            'financeiro.import.template' => 'Baixar modelo de importacao financeira',
            'financeiro.import.preview' => 'Analisar importacao financeira',
            'financeiro.import.show' => 'Visualizar lote de importacao financeira',
            'financeiro.import.process' => 'Processar lote de importacao financeira',
            'financeiro.settings.index' => 'Acessar configuracoes financeiras',
            'financeiro.settings.save' => 'Salvar configuracoes financeiras',
        ];
    }
}
