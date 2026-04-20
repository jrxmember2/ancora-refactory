<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('client_portal_user_condominiums')) {
            Schema::create('client_portal_user_condominiums', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_portal_user_id')->constrained('client_portal_users')->cascadeOnDelete();
                $table->integer('client_condominium_id');
                $table->timestamps();

                $table->unique(['client_portal_user_id', 'client_condominium_id'], 'uniq_portal_user_condominium');
                $table->index('client_condominium_id', 'idx_portal_user_condominiums_condo');

                $table->foreign('client_condominium_id')->references('id')->on('client_condominiums')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('client_portal_users') || !Schema::hasColumn('client_portal_users', 'client_condominium_id')) {
            return;
        }

        DB::table('client_portal_users')
            ->whereNotNull('client_condominium_id')
            ->orderBy('id')
            ->select(['id', 'client_condominium_id'])
            ->chunkById(100, function ($users) {
                foreach ($users as $user) {
                    DB::table('client_portal_user_condominiums')->updateOrInsert(
                        [
                            'client_portal_user_id' => $user->id,
                            'client_condominium_id' => $user->client_condominium_id,
                        ],
                        [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portal_user_condominiums');
    }
};
