<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('calendar_subscriptions') || !Schema::hasTable('calendar_connections')) {
            return;
        }

        Schema::create('calendar_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('connection_id');
            $table->string('provider', 30);
            $table->string('subscription_id', 255);       // Microsoft: subscription id; Google: channel id
            $table->string('resource_id', 255)->nullable(); // Google: resourceId do canal
            $table->string('client_state', 80)->nullable(); // segredo para validar a origem
            $table->text('sync_token')->nullable();          // Google: token de sincronizacao incremental
            $table->dateTime('expires_at')->nullable();
            $table->timestamps();

            $table->unique('connection_id', 'uq_calendar_subscriptions_connection');
            $table->index('subscription_id', 'idx_calendar_subscriptions_subid');
            $table->foreign('connection_id')->references('id')->on('calendar_connections')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_subscriptions');
    }
};
