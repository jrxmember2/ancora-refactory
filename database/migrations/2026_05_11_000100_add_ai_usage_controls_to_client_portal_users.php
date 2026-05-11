<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'client_portal_users';

        if (!Schema::hasTable($table)) {
            return;
        }

        $missingColumns = collect([
            'ai_enabled',
            'ai_monthly_question_limit',
            'ai_questions_used_current_month',
            'ai_usage_reset_at',
            'ai_internal_note',
        ])->filter(fn (string $column) => !Schema::hasColumn($table, $column))->values();

        if ($missingColumns->isEmpty()) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($missingColumns): void {
            if ($missingColumns->contains('ai_enabled')) {
                $table->boolean('ai_enabled')->default(false)->after('can_view_financial_summary');
            }

            if ($missingColumns->contains('ai_monthly_question_limit')) {
                $table->unsignedInteger('ai_monthly_question_limit')->nullable()->after('ai_enabled');
            }

            if ($missingColumns->contains('ai_questions_used_current_month')) {
                $table->unsignedInteger('ai_questions_used_current_month')->default(0)->after('ai_monthly_question_limit');
            }

            if ($missingColumns->contains('ai_usage_reset_at')) {
                $table->date('ai_usage_reset_at')->nullable()->after('ai_questions_used_current_month');
            }

            if ($missingColumns->contains('ai_internal_note')) {
                $table->text('ai_internal_note')->nullable()->after('ai_usage_reset_at');
            }
        });
    }

    public function down(): void
    {
        $table = 'client_portal_users';

        if (!Schema::hasTable($table)) {
            return;
        }

        $existingColumns = collect([
            'ai_enabled',
            'ai_monthly_question_limit',
            'ai_questions_used_current_month',
            'ai_usage_reset_at',
            'ai_internal_note',
        ])->filter(fn (string $column) => Schema::hasColumn($table, $column))->values()->all();

        if ($existingColumns === []) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($existingColumns): void {
            $table->dropColumn($existingColumns);
        });
    }
};
