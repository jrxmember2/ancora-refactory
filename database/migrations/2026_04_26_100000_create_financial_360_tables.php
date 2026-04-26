<?php

use App\Support\Financeiro\FinancialCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('financial_categories')) {
            Schema::create('financial_categories', function (Blueprint $table) {
                $table->id();
                $table->string('type', 20)->default('receita');
                $table->string('code', 60)->nullable()->unique();
                $table->string('name', 180)->unique();
                $table->string('description', 255)->nullable();
                $table->string('dre_group', 80)->nullable();
                $table->string('color_hex', 20)->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('financial_cost_centers')) {
            Schema::create('financial_cost_centers', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->nullable()->unique();
                $table->string('name', 180)->unique();
                $table->string('description', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('financial_accounts')) {
            Schema::create('financial_accounts', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->nullable()->unique();
                $table->string('name', 180);
                $table->string('bank_name', 180)->nullable();
                $table->string('agency', 40)->nullable();
                $table->string('account_number', 60)->nullable();
                $table->string('account_digit', 10)->nullable();
                $table->string('account_type', 40)->default('conta_corrente');
                $table->string('pix_key', 180)->nullable();
                $table->string('account_holder', 180)->nullable();
                $table->decimal('opening_balance', 14, 2)->default(0);
                $table->decimal('credit_limit', 14, 2)->default(0);
                $table->boolean('is_primary')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['account_type', 'is_active'], 'idx_fin_accounts_type_active');
            });
        }

        if (!Schema::hasTable('financial_settings')) {
            Schema::create('financial_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key', 120)->unique();
                $table->longText('value')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('financial_receivables')) {
            Schema::create('financial_receivables', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->nullable()->unique();
                $table->string('title', 220);
                $table->string('reference', 120)->nullable();
                $table->string('billing_type', 60)->nullable();
                $table->integer('client_id')->nullable();
                $table->integer('condominium_id')->nullable();
                $table->integer('unit_id')->nullable();
                $table->unsignedBigInteger('contract_id')->nullable();
                $table->unsignedBigInteger('proposal_id')->nullable();
                $table->unsignedBigInteger('process_id')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->unsignedBigInteger('cost_center_id')->nullable();
                $table->unsignedBigInteger('account_id')->nullable();
                $table->decimal('original_amount', 14, 2)->default(0);
                $table->decimal('interest_amount', 14, 2)->default(0);
                $table->decimal('penalty_amount', 14, 2)->default(0);
                $table->decimal('correction_amount', 14, 2)->default(0);
                $table->decimal('discount_amount', 14, 2)->default(0);
                $table->decimal('final_amount', 14, 2)->default(0);
                $table->decimal('received_amount', 14, 2)->default(0);
                $table->date('due_date')->nullable();
                $table->date('competence_date')->nullable();
                $table->dateTime('received_at')->nullable();
                $table->string('payment_method', 60)->nullable();
                $table->string('status', 40)->default('aberto');
                $table->string('collection_stage', 40)->nullable();
                $table->dateTime('last_collection_at')->nullable();
                $table->boolean('generate_collection')->default(false);
                $table->string('final_pdf_path', 255)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('responsible_user_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'due_date'], 'idx_fin_receivables_status_due');
                $table->index(['client_id', 'condominium_id', 'unit_id'], 'idx_fin_receivables_links');

                $table->foreign('client_id')->references('id')->on('client_entities')->nullOnDelete();
                $table->foreign('condominium_id')->references('id')->on('client_condominiums')->nullOnDelete();
                $table->foreign('unit_id')->references('id')->on('client_units')->nullOnDelete();
                $table->foreign('contract_id')->references('id')->on('contracts')->nullOnDelete();
                $table->foreign('category_id')->references('id')->on('financial_categories')->nullOnDelete();
                $table->foreign('cost_center_id')->references('id')->on('financial_cost_centers')->nullOnDelete();
                $table->foreign('account_id')->references('id')->on('financial_accounts')->nullOnDelete();
                $table->foreign('responsible_user_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('financial_payables')) {
            Schema::create('financial_payables', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->nullable()->unique();
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
                $table->timestamps();
                $table->softDeletes();

                $table->index(['status', 'due_date'], 'idx_fin_payables_status_due');

                $table->foreign('supplier_entity_id')->references('id')->on('client_entities')->nullOnDelete();
                $table->foreign('category_id')->references('id')->on('financial_categories')->nullOnDelete();
                $table->foreign('cost_center_id')->references('id')->on('financial_cost_centers')->nullOnDelete();
                $table->foreign('account_id')->references('id')->on('financial_accounts')->nullOnDelete();
                $table->foreign('responsible_user_id')->references('id')->on('users')->nullOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('financial_reimbursements')) {
            Schema::create('financial_reimbursements', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->nullable()->unique();
                $table->integer('client_id')->nullable();
                $table->unsignedBigInteger('process_id')->nullable();
                $table->string('type', 80)->nullable();
                $table->decimal('amount', 14, 2)->default(0);
                $table->decimal('paid_by_office_amount', 14, 2)->default(0);
                $table->decimal('reimbursed_amount', 14, 2)->default(0);
                $table->date('due_date')->nullable();
                $table->dateTime('reimbursed_at')->nullable();
                $table->string('status', 40)->default('pendente');
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('responsible_user_id')->nullable();
                $table->timestamps();

                $table->foreign('client_id')->references('id')->on('client_entities')->nullOnDelete();
                $table->foreign('responsible_user_id')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('financial_process_costs')) {
            Schema::create('financial_process_costs', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->nullable()->unique();
                $table->unsignedBigInteger('process_id')->nullable();
                $table->integer('client_id')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->unsignedBigInteger('cost_center_id')->nullable();
                $table->unsignedBigInteger('reimbursement_id')->nullable();
                $table->string('cost_type', 80)->nullable();
                $table->decimal('amount', 14, 2)->default(0);
                $table->decimal('reimbursed_amount', 14, 2)->default(0);
                $table->date('cost_date')->nullable();
                $table->string('status', 40)->default('lancado');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('client_id')->references('id')->on('client_entities')->nullOnDelete();
                $table->foreign('category_id')->references('id')->on('financial_categories')->nullOnDelete();
                $table->foreign('cost_center_id')->references('id')->on('financial_cost_centers')->nullOnDelete();
                $table->foreign('reimbursement_id')->references('id')->on('financial_reimbursements')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('financial_installments')) {
            Schema::create('financial_installments', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->nullable()->unique();
                $table->string('title', 220)->nullable();
                $table->integer('client_id')->nullable();
                $table->integer('condominium_id')->nullable();
                $table->integer('unit_id')->nullable();
                $table->unsignedBigInteger('contract_id')->nullable();
                $table->unsignedBigInteger('parent_receivable_id')->nullable();
                $table->unsignedBigInteger('receivable_id')->nullable();
                $table->unsignedInteger('installment_number')->default(1);
                $table->unsignedInteger('installment_total')->default(1);
                $table->decimal('amount', 14, 2)->default(0);
                $table->date('due_date')->nullable();
                $table->string('status', 40)->default('aberto');
                $table->timestamps();

                $table->foreign('client_id')->references('id')->on('client_entities')->nullOnDelete();
                $table->foreign('condominium_id')->references('id')->on('client_condominiums')->nullOnDelete();
                $table->foreign('unit_id')->references('id')->on('client_units')->nullOnDelete();
                $table->foreign('contract_id')->references('id')->on('contracts')->nullOnDelete();
                $table->foreign('parent_receivable_id')->references('id')->on('financial_receivables')->nullOnDelete();
                $table->foreign('receivable_id')->references('id')->on('financial_receivables')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('financial_transactions')) {
            Schema::create('financial_transactions', function (Blueprint $table) {
                $table->id();
                $table->string('code', 60)->nullable()->unique();
                $table->string('transaction_type', 40)->default('entrada');
                $table->unsignedBigInteger('account_id')->nullable();
                $table->unsignedBigInteger('destination_account_id')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->unsignedBigInteger('cost_center_id')->nullable();
                $table->unsignedBigInteger('receivable_id')->nullable();
                $table->unsignedBigInteger('payable_id')->nullable();
                $table->unsignedBigInteger('reimbursement_id')->nullable();
                $table->unsignedBigInteger('process_cost_id')->nullable();
                $table->unsignedBigInteger('installment_id')->nullable();
                $table->unsignedBigInteger('contract_id')->nullable();
                $table->decimal('amount', 14, 2)->default(0);
                $table->dateTime('transaction_date')->nullable();
                $table->string('source', 120)->nullable();
                $table->string('document_number', 120)->nullable();
                $table->string('payment_method', 60)->nullable();
                $table->string('reconciliation_status', 40)->default('pendente');
                $table->dateTime('reconciled_at')->nullable();
                $table->unsignedBigInteger('reconciled_by')->nullable();
                $table->text('description')->nullable();
                $table->json('metadata_json')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['transaction_type', 'transaction_date'], 'idx_fin_transactions_type_date');

                $table->foreign('account_id')->references('id')->on('financial_accounts')->nullOnDelete();
                $table->foreign('destination_account_id')->references('id')->on('financial_accounts')->nullOnDelete();
                $table->foreign('category_id')->references('id')->on('financial_categories')->nullOnDelete();
                $table->foreign('cost_center_id')->references('id')->on('financial_cost_centers')->nullOnDelete();
                $table->foreign('receivable_id')->references('id')->on('financial_receivables')->nullOnDelete();
                $table->foreign('payable_id')->references('id')->on('financial_payables')->nullOnDelete();
                $table->foreign('reimbursement_id')->references('id')->on('financial_reimbursements')->nullOnDelete();
                $table->foreign('process_cost_id')->references('id')->on('financial_process_costs')->nullOnDelete();
                $table->foreign('installment_id')->references('id')->on('financial_installments')->nullOnDelete();
                $table->foreign('contract_id')->references('id')->on('contracts')->nullOnDelete();
                $table->foreign('reconciled_by')->references('id')->on('users')->nullOnDelete();
                $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('financial_cash_flow')) {
            Schema::create('financial_cash_flow', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->nullable();
                $table->unsignedBigInteger('receivable_id')->nullable();
                $table->unsignedBigInteger('payable_id')->nullable();
                $table->unsignedBigInteger('transaction_id')->nullable();
                $table->string('kind', 20)->default('real');
                $table->string('direction', 20)->default('entrada');
                $table->decimal('amount', 14, 2)->default(0);
                $table->dateTime('movement_date')->nullable();
                $table->string('status', 40)->nullable();
                $table->string('source_label', 220)->nullable();
                $table->text('description')->nullable();
                $table->timestamps();

                $table->foreign('account_id')->references('id')->on('financial_accounts')->nullOnDelete();
                $table->foreign('receivable_id')->references('id')->on('financial_receivables')->nullOnDelete();
                $table->foreign('payable_id')->references('id')->on('financial_payables')->nullOnDelete();
                $table->foreign('transaction_id')->references('id')->on('financial_transactions')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('financial_import_logs')) {
            Schema::create('financial_import_logs', function (Blueprint $table) {
                $table->id();
                $table->string('scope', 60);
                $table->string('source_format', 20);
                $table->string('original_name', 255)->nullable();
                $table->string('stored_path', 255)->nullable();
                $table->unsignedInteger('preview_rows_count')->default(0);
                $table->unsignedInteger('success_rows')->default(0);
                $table->unsignedInteger('failed_rows')->default(0);
                $table->string('status', 40)->default('preview');
                $table->json('payload_json')->nullable();
                $table->json('errors_json')->nullable();
                $table->unsignedBigInteger('processed_by')->nullable();
                $table->dateTime('processed_at')->nullable();
                $table->timestamps();

                $table->foreign('processed_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('financial_statements')) {
            Schema::create('financial_statements', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->nullable();
                $table->unsignedBigInteger('import_log_id')->nullable();
                $table->dateTime('statement_date')->nullable();
                $table->string('description', 255)->nullable();
                $table->string('document_number', 120)->nullable();
                $table->decimal('amount', 14, 2)->default(0);
                $table->decimal('balance_after', 14, 2)->nullable();
                $table->string('direction', 20)->default('entrada');
                $table->string('raw_hash', 120)->nullable()->unique();
                $table->boolean('is_reconciled')->default(false);
                $table->json('payload_json')->nullable();
                $table->timestamps();

                $table->foreign('account_id')->references('id')->on('financial_accounts')->nullOnDelete();
                $table->foreign('import_log_id')->references('id')->on('financial_import_logs')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('financial_reconciliations')) {
            Schema::create('financial_reconciliations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('statement_id');
                $table->unsignedBigInteger('transaction_id')->nullable();
                $table->string('result', 40)->default('pendente');
                $table->decimal('matched_amount', 14, 2)->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('reconciled_by')->nullable();
                $table->dateTime('reconciled_at')->nullable();
                $table->timestamps();

                $table->foreign('statement_id')->references('id')->on('financial_statements')->cascadeOnDelete();
                $table->foreign('transaction_id')->references('id')->on('financial_transactions')->nullOnDelete();
                $table->foreign('reconciled_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('financial_reports')) {
            Schema::create('financial_reports', function (Blueprint $table) {
                $table->id();
                $table->string('report_type', 80);
                $table->string('title', 220);
                $table->json('filters_json')->nullable();
                $table->json('summary_json')->nullable();
                $table->string('file_path', 255)->nullable();
                $table->unsignedBigInteger('generated_by')->nullable();
                $table->dateTime('generated_at')->nullable();
                $table->timestamps();

                $table->foreign('generated_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('financial_attachments')) {
            Schema::create('financial_attachments', function (Blueprint $table) {
                $table->id();
                $table->string('owner_type', 60);
                $table->unsignedBigInteger('owner_id');
                $table->string('original_name', 255);
                $table->string('stored_name', 255);
                $table->string('relative_path', 255);
                $table->string('file_type', 40)->nullable();
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->string('description', 255)->nullable();
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();

                $table->index(['owner_type', 'owner_id'], 'idx_fin_attachments_owner');
                $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        $this->seedModule();
        $this->seedRoutePermissions();
        $this->seedSettings();
        $this->seedCategories();
        $this->seedCostCenters();
        $this->seedAccounts();
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_attachments');
        Schema::dropIfExists('financial_reports');
        Schema::dropIfExists('financial_reconciliations');
        Schema::dropIfExists('financial_statements');
        Schema::dropIfExists('financial_import_logs');
        Schema::dropIfExists('financial_cash_flow');
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('financial_installments');
        Schema::dropIfExists('financial_process_costs');
        Schema::dropIfExists('financial_reimbursements');
        Schema::dropIfExists('financial_payables');
        Schema::dropIfExists('financial_receivables');
        Schema::dropIfExists('financial_settings');
        Schema::dropIfExists('financial_accounts');
        Schema::dropIfExists('financial_cost_centers');
        Schema::dropIfExists('financial_categories');

        if (Schema::hasTable('system_modules')) {
            DB::table('system_modules')->where('slug', 'financeiro')->delete();
        }

        if (Schema::hasTable('route_permissions')) {
            DB::table('route_permissions')->whereIn('route_name', array_keys($this->routePermissions()))->delete();
        }
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
};
