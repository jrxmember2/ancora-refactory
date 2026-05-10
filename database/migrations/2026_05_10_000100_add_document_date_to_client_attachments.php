<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('client_attachments') || Schema::hasColumn('client_attachments', 'document_date')) {
            return;
        }

        Schema::table('client_attachments', function (Blueprint $table) {
            $table->date('document_date')->nullable()->after('created_at');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('client_attachments') || !Schema::hasColumn('client_attachments', 'document_date')) {
            return;
        }

        Schema::table('client_attachments', function (Blueprint $table) {
            $table->dropColumn('document_date');
        });
    }
};
