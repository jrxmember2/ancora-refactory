<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_chat_messages')) {
            Schema::table('ai_chat_messages', function (Blueprint $table) {
                if (!Schema::hasColumn('ai_chat_messages', 'tokens_total')) {
                    $table->unsignedInteger('tokens_total')->nullable()->after('output_tokens');
                }

                if (!Schema::hasColumn('ai_chat_messages', 'error_message')) {
                    $table->text('error_message')->nullable()->after('error');
                }

                if (!Schema::hasColumn('ai_chat_messages', 'is_relevant')) {
                    $table->boolean('is_relevant')->default(false)->after('meta_json');
                }

                if (!Schema::hasColumn('ai_chat_messages', 'requires_legal_review')) {
                    $table->boolean('requires_legal_review')->default(false)->after('is_relevant');
                }

                if (!Schema::hasColumn('ai_chat_messages', 'is_faq_candidate')) {
                    $table->boolean('is_faq_candidate')->default(false)->after('requires_legal_review');
                }

                if (!Schema::hasColumn('ai_chat_messages', 'internal_note')) {
                    $table->text('internal_note')->nullable()->after('is_faq_candidate');
                }
            });

            if (Schema::hasColumn('ai_chat_messages', 'tokens_total')) {
                DB::table('ai_chat_messages')
                    ->whereNull('tokens_total')
                    ->update([
                        'tokens_total' => DB::raw('CASE
                            WHEN input_tokens IS NOT NULL OR output_tokens IS NOT NULL
                                THEN COALESCE(input_tokens, 0) + COALESCE(output_tokens, 0)
                            ELSE token_estimate
                        END'),
                    ]);
            }

            if (Schema::hasColumn('ai_chat_messages', 'error_message') && Schema::hasColumn('ai_chat_messages', 'error')) {
                DB::table('ai_chat_messages')
                    ->whereNull('error_message')
                    ->whereNotNull('error')
                    ->update([
                        'error_message' => DB::raw('error'),
                    ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_chat_messages')) {
            Schema::table('ai_chat_messages', function (Blueprint $table) {
                if (Schema::hasColumn('ai_chat_messages', 'internal_note')) {
                    $table->dropColumn('internal_note');
                }

                if (Schema::hasColumn('ai_chat_messages', 'is_faq_candidate')) {
                    $table->dropColumn('is_faq_candidate');
                }

                if (Schema::hasColumn('ai_chat_messages', 'requires_legal_review')) {
                    $table->dropColumn('requires_legal_review');
                }

                if (Schema::hasColumn('ai_chat_messages', 'is_relevant')) {
                    $table->dropColumn('is_relevant');
                }

                if (Schema::hasColumn('ai_chat_messages', 'error_message')) {
                    $table->dropColumn('error_message');
                }

                if (Schema::hasColumn('ai_chat_messages', 'tokens_total')) {
                    $table->dropColumn('tokens_total');
                }
            });
        }
    }
};
