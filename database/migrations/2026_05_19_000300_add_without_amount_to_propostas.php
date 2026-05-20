<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('propostas')) {
            return;
        }

        Schema::table('propostas', function (Blueprint $table) {
            if (!Schema::hasColumn('propostas', 'without_amount')) {
                $table->boolean('without_amount')->default(false)->after('closed_total');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('propostas') || !Schema::hasColumn('propostas', 'without_amount')) {
            return;
        }

        Schema::table('propostas', function (Blueprint $table) {
            $table->dropColumn('without_amount');
        });
    }
};
