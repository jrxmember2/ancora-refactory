<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cobranca_import_rows')) {
            Schema::table('cobranca_import_rows', function (Blueprint $table) {
                if (!Schema::hasColumn('cobranca_import_rows', 'owner_input')) {
                    $table->string('owner_input', 180)->nullable()->after('unit_input');
                }
                if (!Schema::hasColumn('cobranca_import_rows', 'quota_type_input')) {
                    $table->string('quota_type_input', 40)->nullable()->after('amount_value');
                }
                if (!Schema::hasColumn('cobranca_import_rows', 'issue_code')) {
                    $table->string('issue_code', 60)->nullable()->after('status');
                }
                if (!Schema::hasColumn('cobranca_import_rows', 'issue_payload_json')) {
                    $table->json('issue_payload_json')->nullable()->after('issue_code');
                }
                if (!Schema::hasColumn('cobranca_import_rows', 'resolution_payload_json')) {
                    $table->json('resolution_payload_json')->nullable()->after('issue_payload_json');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('cobranca_import_rows')) {
            Schema::table('cobranca_import_rows', function (Blueprint $table) {
                foreach (['resolution_payload_json', 'issue_payload_json', 'issue_code', 'quota_type_input', 'owner_input'] as $column) {
                    if (Schema::hasColumn('cobranca_import_rows', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
