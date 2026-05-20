<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('process_case_parties')) {
            return;
        }

        Schema::create('process_case_parties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('process_case_id')->constrained('process_cases')->cascadeOnDelete();
            $table->string('party_type', 20);
            $table->unsignedInteger('entity_id')->nullable();
            $table->string('name_snapshot', 190);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['process_case_id', 'party_type', 'sort_order'], 'idx_process_case_parties_case_type_order');
            $table->foreign('entity_id')->references('id')->on('client_entities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('process_case_parties');
    }
};
