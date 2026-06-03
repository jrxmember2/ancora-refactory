<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('calendar_connections')) {
            Schema::create('calendar_connections', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->string('provider', 30);
                $table->string('account_email', 190)->nullable();
                $table->text('access_token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->dateTime('token_expires_at')->nullable();
                $table->text('scopes')->nullable();
                $table->string('calendar_id', 190)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['user_id', 'provider'], 'uq_calendar_connections_user_provider');
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('agenda_event_syncs')) {
            Schema::create('agenda_event_syncs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('agenda_event_id');
                $table->unsignedBigInteger('connection_id');
                $table->string('provider', 30);
                $table->string('external_event_id', 255);
                $table->dateTime('last_synced_at')->nullable();
                $table->timestamps();

                $table->unique(['agenda_event_id', 'connection_id'], 'uq_agenda_event_syncs');
                $table->foreign('agenda_event_id')->references('id')->on('agenda_events')->cascadeOnDelete();
                $table->foreign('connection_id')->references('id')->on('calendar_connections')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_event_syncs');
        Schema::dropIfExists('calendar_connections');
    }
};
