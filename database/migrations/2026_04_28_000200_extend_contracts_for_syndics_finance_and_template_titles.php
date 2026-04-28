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
        }

        if (Schema::hasTable('contract_templates') && Schema::hasColumn('contract_templates', 'default_contract_title')) {
            DB::table('contract_templates')
                ->where(function ($query) {
                    $query->whereNull('default_contract_title')
                        ->orWhere('default_contract_title', '');
                })
                ->update([
                    'default_contract_title' => DB::raw("COALESCE(NULLIF(name, ''), document_type)"),
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('contracts')) {
            if (!Schema::hasColumn('contracts', 'syndico_entity_id')) {
                Schema::table('contracts', function (Blueprint $table) {
                    $table->integer('syndico_entity_id')->nullable()->after('condominium_id');
                    $table->index('syndico_entity_id', 'idx_contracts_syndic');
                });

                Schema::table('contracts', function (Blueprint $table) {
                    $table->foreign('syndico_entity_id', 'fk_contracts_syndic')
                        ->references('id')
                        ->on('client_entities')
                        ->nullOnDelete();
                });
            }

            if (!Schema::hasColumn('contracts', 'financial_account_id')) {
                Schema::table('contracts', function (Blueprint $table) {
                    $table->unsignedBigInteger('financial_account_id')->nullable()->after('generate_financial_entries');
                    $table->index('financial_account_id', 'idx_contracts_financial_account');
                });

                if (Schema::hasTable('financial_accounts')) {
                    Schema::table('contracts', function (Blueprint $table) {
                        $table->foreign('financial_account_id', 'fk_contracts_financial_account')
                            ->references('id')
                            ->on('financial_accounts')
                            ->nullOnDelete();
                    });
                }
            }

            if (!Schema::hasColumn('contracts', 'payment_method')) {
                Schema::table('contracts', function (Blueprint $table) {
                    $table->string('payment_method', 60)->nullable()->after('financial_account_id');
                });
            }
        }

        $this->seedVariables();
    }

    public function down(): void
    {
        if (Schema::hasTable('contracts')) {
            if (Schema::hasColumn('contracts', 'payment_method')) {
                Schema::table('contracts', function (Blueprint $table) {
                    $table->dropColumn('payment_method');
                });
            }

            if (Schema::hasColumn('contracts', 'financial_account_id')) {
                Schema::table('contracts', function (Blueprint $table) {
                    try {
                        $table->dropForeign('fk_contracts_financial_account');
                    } catch (\Throwable) {
                    }
                    $table->dropIndex('idx_contracts_financial_account');
                    $table->dropColumn('financial_account_id');
                });
            }

            if (Schema::hasColumn('contracts', 'syndico_entity_id')) {
                Schema::table('contracts', function (Blueprint $table) {
                    try {
                        $table->dropForeign('fk_contracts_syndic');
                    } catch (\Throwable) {
                    }
                    $table->dropIndex('idx_contracts_syndic');
                    $table->dropColumn('syndico_entity_id');
                });
            }
        }

        if (Schema::hasTable('contract_templates') && Schema::hasColumn('contract_templates', 'default_contract_title')) {
            Schema::table('contract_templates', function (Blueprint $table) {
                $table->dropColumn('default_contract_title');
            });
        }
    }

    private function seedVariables(): void
    {
        if (!Schema::hasTable('contract_variables')) {
            return;
        }

        foreach (ContractVariableCatalog::definitions() as $index => $variable) {
            $exists = DB::table('contract_variables')->where('key', $variable['key'])->exists();

            DB::table('contract_variables')->updateOrInsert(
                ['key' => $variable['key']],
                [
                    'label' => $variable['label'],
                    'description' => $variable['description'],
                    'source' => $variable['source'],
                    'is_active' => true,
                    'sort_order' => ($index + 1) * 10,
                    'updated_at' => now(),
                    'created_at' => $exists ? DB::raw('created_at') : now(),
                ]
            );
        }
    }
};
