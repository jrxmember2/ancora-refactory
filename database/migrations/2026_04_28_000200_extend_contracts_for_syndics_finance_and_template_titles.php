<?php

use App\Support\Contracts\ContractVariableCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_templates') && !Schema::hasColumn('contract_templates', 'default_contract_title')) {
            Schema::table('contract_templates', function (Blueprint $table) {
                $table->string('default_contract_title', 220)->nullable()->after('document_type');
            });

            DB::table('contract_templates')->whereNull('default_contract_title')->update([
                'default_contract_title' => DB::raw('name'),
            ]);
        }

        if (Schema::hasTable('contracts')) {
            Schema::table('contracts', function (Blueprint $table) {
                if (!Schema::hasColumn('contracts', 'syndico_entity_id')) {
                    $table->integer('syndico_entity_id')->nullable()->after('condominium_id');
                    $table->index('syndico_entity_id', 'idx_contracts_syndic');
                }

                if (!Schema::hasColumn('contracts', 'financial_account_id')) {
                    $table->unsignedBigInteger('financial_account_id')->nullable()->after('generate_financial_entries');
                    $table->index('financial_account_id', 'idx_contracts_financial_account');
                }

                if (!Schema::hasColumn('contracts', 'payment_method')) {
                    $table->string('payment_method', 60)->nullable()->after('financial_account_id');
                }
            });

            if (!$this->foreignExists('contracts', 'fk_contracts_syndic')) {
                Schema::table('contracts', function (Blueprint $table) {
                    $table->foreign('syndico_entity_id', 'fk_contracts_syndic')
                        ->references('id')
                        ->on('client_entities')
                        ->nullOnDelete();
                });
            }

            if (Schema::hasTable('financial_accounts') && !$this->foreignExists('contracts', 'fk_contracts_financial_account')) {
                Schema::table('contracts', function (Blueprint $table) {
                    $table->foreign('financial_account_id', 'fk_contracts_financial_account')
                        ->references('id')
                        ->on('financial_accounts')
                        ->nullOnDelete();
                });
            }
        }

        if (Schema::hasTable('contract_variables')) {
            foreach (ContractVariableCatalog::definitions() as $index => $variable) {
                DB::table('contract_variables')->updateOrInsert(
                    ['key' => $variable['key']],
                    [
                        'label' => $variable['label'],
                        'description' => $variable['description'] ?? null,
                        'source' => $variable['source'] ?? null,
                        'is_active' => true,
                        'sort_order' => $index + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contracts')) {
            if ($this->foreignExists('contracts', 'fk_contracts_financial_account')) {
                Schema::table('contracts', function (Blueprint $table) {
                    $table->dropForeign('fk_contracts_financial_account');
                });
            }

            if ($this->foreignExists('contracts', 'fk_contracts_syndic')) {
                Schema::table('contracts', function (Blueprint $table) {
                    $table->dropForeign('fk_contracts_syndic');
                });
            }

            Schema::table('contracts', function (Blueprint $table) {
                if (Schema::hasColumn('contracts', 'payment_method')) {
                    $table->dropColumn('payment_method');
                }

                if (Schema::hasColumn('contracts', 'financial_account_id')) {
                    $table->dropIndex('idx_contracts_financial_account');
                    $table->dropColumn('financial_account_id');
                }

                if (Schema::hasColumn('contracts', 'syndico_entity_id')) {
                    $table->dropIndex('idx_contracts_syndic');
                    $table->dropColumn('syndico_entity_id');
                }
            });
        }

        if (Schema::hasTable('contract_templates') && Schema::hasColumn('contract_templates', 'default_contract_title')) {
            Schema::table('contract_templates', function (Blueprint $table) {
                $table->dropColumn('default_contract_title');
            });
        }
    }

    private function foreignExists(string $table, string $name): bool
    {
        $database = DB::getDatabaseName();

        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $name)
            ->exists();
    }
};
