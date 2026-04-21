<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('demands')) {
            Schema::table('demands', function (Blueprint $table) {
                if (!Schema::hasColumn('demands', 'automation_session_id')) {
                    $table->foreignId('automation_session_id')
                        ->nullable()
                        ->after('cobranca_case_id')
                        ->constrained('automation_sessions')
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('demands', 'automation_agreement_proposal_id')) {
                    $table->foreignId('automation_agreement_proposal_id')
                        ->nullable()
                        ->after('automation_session_id')
                        ->constrained('automation_agreement_proposals')
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('demands', 'sla_due_at')) {
                    $table->dateTime('sla_due_at')->nullable()->after('closed_at');
                }
            });
        }

        if (Schema::hasTable('cobranca_monetary_updates')) {
            Schema::table('cobranca_monetary_updates', function (Blueprint $table) {
                if (!Schema::hasColumn('cobranca_monetary_updates', 'boleto_fee_total')) {
                    $table->decimal('boleto_fee_total', 12, 2)->default(0)->after('costs_corrected_amount');
                }

                if (!Schema::hasColumn('cobranca_monetary_updates', 'boleto_cancellation_fee_total')) {
                    $table->decimal('boleto_cancellation_fee_total', 12, 2)->default(0)->after('boleto_fee_total');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cobranca_monetary_updates')) {
            Schema::table('cobranca_monetary_updates', function (Blueprint $table) {
                if (Schema::hasColumn('cobranca_monetary_updates', 'boleto_cancellation_fee_total')) {
                    $table->dropColumn('boleto_cancellation_fee_total');
                }

                if (Schema::hasColumn('cobranca_monetary_updates', 'boleto_fee_total')) {
                    $table->dropColumn('boleto_fee_total');
                }
            });
        }

        if (Schema::hasTable('demands')) {
            Schema::table('demands', function (Blueprint $table) {
                if (Schema::hasColumn('demands', 'sla_due_at')) {
                    $table->dropColumn('sla_due_at');
                }

                if (Schema::hasColumn('demands', 'automation_agreement_proposal_id')) {
                    $table->dropConstrainedForeignId('automation_agreement_proposal_id');
                }

                if (Schema::hasColumn('demands', 'automation_session_id')) {
                    $table->dropConstrainedForeignId('automation_session_id');
                }
            });
        }
    }
};
