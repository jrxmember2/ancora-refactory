<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('process_cases') || Schema::hasColumn('process_cases', 'judging_body')) {
            return;
        }

        Schema::table('process_cases', function (Blueprint $table) {
            $table->string('judging_body', 190)->nullable()->after('nature_option_id');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('process_cases') || !Schema::hasColumn('process_cases', 'judging_body')) {
            return;
        }

        Schema::table('process_cases', function (Blueprint $table) {
            $table->dropColumn('judging_body');
        });
    }
};
