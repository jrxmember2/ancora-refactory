<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contract_templates') && !Schema::hasColumn('contract_templates', 'page_size')) {
            Schema::table('contract_templates', function (Blueprint $table) {
                $table->string('page_size', 20)->default('a4')->after('page_orientation');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('contract_templates') && Schema::hasColumn('contract_templates', 'page_size')) {
            Schema::table('contract_templates', function (Blueprint $table) {
                $table->dropColumn('page_size');
            });
        }
    }
};
