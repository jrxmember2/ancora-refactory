<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('client_attachments')) {
            return;
        }

        Schema::table('client_attachments', function (Blueprint $table) {
            if (!Schema::hasColumn('client_attachments', 'ai_processing_status')) {
                $table->string('ai_processing_status', 40)->nullable();
            }

            if (!Schema::hasColumn('client_attachments', 'ai_processing_error')) {
                $table->longText('ai_processing_error')->nullable();
            }

            if (!Schema::hasColumn('client_attachments', 'ai_processed_at')) {
                $table->timestamp('ai_processed_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('client_attachments')) {
            return;
        }

        Schema::table('client_attachments', function (Blueprint $table) {
            if (Schema::hasColumn('client_attachments', 'ai_processed_at')) {
                $table->dropColumn('ai_processed_at');
            }

            if (Schema::hasColumn('client_attachments', 'ai_processing_error')) {
                $table->dropColumn('ai_processing_error');
            }

            if (Schema::hasColumn('client_attachments', 'ai_processing_status')) {
                $table->dropColumn('ai_processing_status');
            }
        });
    }
};
