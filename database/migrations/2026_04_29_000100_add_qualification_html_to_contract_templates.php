<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_templates') && !Schema::hasColumn('contract_templates', 'qualification_html')) {
            Schema::table('contract_templates', function (Blueprint $table) {
                $table->longText('qualification_html')->nullable()->after('header_html');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contract_templates') && Schema::hasColumn('contract_templates', 'qualification_html')) {
            Schema::table('contract_templates', function (Blueprint $table) {
                $table->dropColumn('qualification_html');
            });
        }
    }
};
