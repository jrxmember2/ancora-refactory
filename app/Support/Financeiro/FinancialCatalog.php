<?php

namespace App\Support\Financeiro;

class FinancialCatalog
{
    public static function receivableStatuses(): array
    {
        return [
            'aberto' => 'Aberto',
            'recebido' => 'Recebido',
            'parcial' => 'Parcial',
            'vencido' => 'Vencido',
            'negociado' => 'Negociado',
            'cancelado' => 'Cancelado',
            'protestado' => 'Protestado',
        ];
    }

    public static function payableStatuses(): array
    {
        return [
            'aberto' => 'Aberto',
            'pago' => 'Pago',
            'parcial' => 'Parcial',
            'vencido' => 'Vencido',
            'negociado' => 'Negociado',
            'cancelado' => 'Cancelado',
        ];
    }

    public static function transactionTypes(): array
    {
        return [
            'entrada' => 'Entrada',
            'saida' => 'Saida',
            'transferencia' => 'Transferencia',
            'ajuste' => 'Ajuste',
            'reembolso' => 'Reembolso',
            'repasse' => 'Repasse',
        ];
    }

    public static function accountTypes(): array
    {
        return [
            'conta_corrente' => 'Conta corrente',
            'poupanca' => 'Poupanca',
            'caixa' => 'Caixa',
            'carteira' => 'Carteira',
            'cartao' => 'Cartao',
            'banco_digital' => 'Banco digital',
        ];
    }

    public static function paymentMethods(): array
    {
        return [
            'pix' => 'Pix',
            'boleto' => 'Boleto',
            'transferencia' => 'Transferencia',
            'cartao' => 'Cartao',
            'especie' => 'Especie',
            'deposito' => 'Deposito',
            'debito_automatico' => 'Debito automatico',
            'cheque' => 'Cheque',
            'outro' => 'Outro',
        ];
    }

    public static function recurrences(): array
    {
        return [
            'unica' => 'Unica',
            'semanal' => 'Semanal',
            'quinzenal' => 'Quinzenal',
            'mensal' => 'Mensal',
            'bimestral' => 'Bimestral',
            'trimestral' => 'Trimestral',
            'semestral' => 'Semestral',
            'anual' => 'Anual',
        ];
    }

    public static function billingTypes(): array
    {
        return [
            'mensalidade' => 'Mensalidade',
            'honorario' => 'Honorario',
            'exito' => 'Exito',
            'parcela' => 'Parcela',
            'cobranca_condominial' => 'Cobranca condominial',
            'reembolso' => 'Reembolso',
            'receita_extraordinaria' => 'Receita extraordinaria',
        ];
    }

    public static function reimbursementStatuses(): array
    {
        return [
            'pendente' => 'Pendente',
            'parcial' => 'Parcial',
            'reembolsado' => 'Reembolsado',
            'cancelado' => 'Cancelado',
        ];
    }

    public static function processCostStatuses(): array
    {
        return [
            'lancado' => 'Lancado',
            'pago' => 'Pago pelo escritorio',
            'reembolsado' => 'Reembolsado',
            'parcial' => 'Parcial',
            'cancelado' => 'Cancelado',
        ];
    }

    public static function collectionStages(): array
    {
        return [
            'lembrete' => 'Lembrete preventivo',
            'vencimento' => 'No vencimento',
            'leve' => 'Cobranca leve',
            'forte' => 'Cobranca forte',
            'juridico' => 'Encaminhado ao juridico',
        ];
    }

    public static function dreGroups(): array
    {
        return [
            'receita_bruta' => 'Receita bruta',
            'deducoes' => 'Deducoes',
            'custos' => 'Custos',
            'despesas_operacionais' => 'Despesas operacionais',
            'despesas_administrativas' => 'Despesas administrativas',
            'despesas_comerciais' => 'Despesas comerciais',
            'resultado_financeiro' => 'Resultado financeiro',
            'outros_resultados' => 'Outros resultados',
        ];
    }

    public static function settings(): array
    {
        return [
            'default_interest_percent' => '1.00',
            'default_penalty_percent' => '2.00',
            'default_account_id' => '',
            'alert_days' => '7',
            'entry_prefix' => 'FIN',
            'auto_numbering' => '1',
            'default_receivable_status' => 'aberto',
            'default_payable_status' => 'aberto',
            'billing_due_day' => '10',
            'default_city' => 'Vila Velha',
            'default_state' => 'ES',
        ];
    }

    public static function defaultCategories(): array
    {
        return [
            ['type' => 'receita', 'name' => 'Honorarios', 'description' => 'Honorarios advocaticios', 'dre_group' => 'receita_bruta', 'color_hex' => '#10b981'],
            ['type' => 'receita', 'name' => 'Assessoria', 'description' => 'Contratos de assessoria recorrente', 'dre_group' => 'receita_bruta', 'color_hex' => '#16a34a'],
            ['type' => 'receita', 'name' => 'Acordos', 'description' => 'Receitas oriundas de acordos', 'dre_group' => 'receita_bruta', 'color_hex' => '#0ea5e9'],
            ['type' => 'receita', 'name' => 'Reembolso', 'description' => 'Reembolsos recebidos', 'dre_group' => 'receita_bruta', 'color_hex' => '#6366f1'],
            ['type' => 'receita', 'name' => 'Cobranca', 'description' => 'Receitas ligadas ao modulo de cobranca', 'dre_group' => 'receita_bruta', 'color_hex' => '#f59e0b'],
            ['type' => 'despesa', 'name' => 'Custas', 'description' => 'Custas processuais e cartorarias', 'dre_group' => 'custos', 'color_hex' => '#ef4444'],
            ['type' => 'despesa', 'name' => 'Tributos', 'description' => 'Tributos e encargos', 'dre_group' => 'despesas_administrativas', 'color_hex' => '#dc2626'],
            ['type' => 'despesa', 'name' => 'Sistemas', 'description' => 'Softwares, licencas e infraestrutura', 'dre_group' => 'despesas_operacionais', 'color_hex' => '#8b5cf6'],
            ['type' => 'despesa', 'name' => 'Salarios', 'description' => 'Folha e pro-labore', 'dre_group' => 'despesas_administrativas', 'color_hex' => '#f97316'],
            ['type' => 'despesa', 'name' => 'Marketing', 'description' => 'Investimentos em marketing', 'dre_group' => 'despesas_comerciais', 'color_hex' => '#ec4899'],
        ];
    }

    public static function defaultCostCenters(): array
    {
        return [
            ['name' => 'Juridico', 'description' => 'Centro de custo juridico'],
            ['name' => 'Administrativo', 'description' => 'Centro de custo administrativo'],
            ['name' => 'Comercial', 'description' => 'Centro de custo comercial'],
            ['name' => 'Marketing', 'description' => 'Centro de custo marketing'],
            ['name' => 'TI', 'description' => 'Centro de custo de tecnologia'],
            ['name' => 'Condominial', 'description' => 'Centro de custo das operacoes condominiais'],
            ['name' => 'Operacional', 'description' => 'Centro de custo operacional'],
        ];
    }

    public static function defaultAccounts(): array
    {
        return [
            [
                'name' => 'Conta Principal',
                'bank_name' => 'Banco principal',
                'account_type' => 'conta_corrente',
                'agency' => '',
                'account_number' => '',
                'pix_key' => '',
                'opening_balance' => '0.00',
                'credit_limit' => '0.00',
                'is_primary' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Caixa Interno',
                'bank_name' => 'Caixa',
                'account_type' => 'caixa',
                'agency' => '',
                'account_number' => '',
                'pix_key' => '',
                'opening_balance' => '0.00',
                'credit_limit' => '0.00',
                'is_primary' => false,
                'is_active' => true,
            ],
        ];
    }

    public static function importScopes(): array
    {
        return [
            'receivables' => 'Contas a receber',
            'payables' => 'Contas a pagar',
            'categories' => 'Categorias financeiras',
            'cost-centers' => 'Centros de custo',
            'accounts' => 'Bancos e contas',
            'transactions' => 'Movimentacoes financeiras',
            'installments' => 'Parcelamentos',
            'reimbursements' => 'Reembolsos',
            'statements' => 'Extrato bancario',
        ];
    }

    public static function exportScopes(): array
    {
        return [
            'receivables' => 'Contas a receber',
            'payables' => 'Contas a pagar',
            'cash-flow' => 'Fluxo de caixa',
            'accounts' => 'Bancos e contas',
            'collection' => 'Cobrancas',
            'reimbursements' => 'Reembolsos',
            'process-costs' => 'Custas processuais',
            'statements' => 'Conciliacao bancaria',
            'billing' => 'Faturamento',
            'installments' => 'Parcelamentos',
            'categories' => 'Categorias financeiras',
            'cost-centers' => 'Centros de custo',
            'delinquency' => 'Inadimplencia',
            'dre' => 'DRE',
            'accountability' => 'Prestacao de contas',
            'reports' => 'Relatorios financeiros',
        ];
    }
}
