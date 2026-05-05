<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('servicos') || !Schema::hasColumn('servicos', 'is_active')) {
            return;
        }

        DB::table('servicos')
            ->where(function ($query) {
                $query->whereNull('is_active')->orWhere('is_active', 0);
            })
            ->update(['is_active' => 1]);
    }

    public function down(): void
    {
        // Nao desfazemos automaticamente para evitar desligar servicos existentes.
    }
};
