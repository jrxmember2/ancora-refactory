<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hub_device_tokens')) {
            return;
        }

        Schema::create('hub_device_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('hub_api_token_id')->nullable()->constrained('hub_api_tokens')->nullOnDelete();
            $table->longText('fcm_token');
            $table->string('fcm_token_hash', 64)->unique();
            $table->string('platform', 20)->default('android');
            $table->string('device_name', 160)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at'], 'idx_hub_device_tokens_user_revoked');
            $table->index(['hub_api_token_id', 'revoked_at'], 'idx_hub_device_tokens_api_revoked');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_device_tokens');
    }
};
