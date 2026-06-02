<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('contracts') || Schema::hasColumn('contracts', 'success_fee_percentage')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->decimal('success_fee_percentage', 7, 2)->nullable()->after('penalty_percentage');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('contracts') || !Schema::hasColumn('contracts', 'success_fee_percentage')) {
            return;
        }

        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('success_fee_percentage');
        });
    }
};
