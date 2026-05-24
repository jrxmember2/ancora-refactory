<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hub_api_tokens')) {
            return;
        }

        Schema::create('hub_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120)->default('ancora-hub-android');
            $table->string('token_hash', 64)->unique();
            $table->json('abilities_json')->nullable();
            $table->string('device_name', 160)->nullable();
            $table->string('platform', 20)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('biometric_enabled')->default(false);
            $table->dateTime('last_used_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at'], 'idx_hub_api_tokens_user_revoked');
            $table->index(['expires_at'], 'idx_hub_api_tokens_expires_at');
            $table->index(['last_used_at'], 'idx_hub_api_tokens_last_used_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_api_tokens');
    }
};
