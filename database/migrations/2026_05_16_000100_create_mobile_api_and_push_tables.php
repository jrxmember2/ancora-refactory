<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('client_portal_api_tokens')) {
            Schema::create('client_portal_api_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_portal_user_id')->constrained('client_portal_users')->cascadeOnDelete();
                $table->string('name', 120)->default('android');
                $table->string('platform', 20)->default('android');
                $table->string('device_name', 160)->nullable();
                $table->string('app_version', 40)->nullable();
                $table->string('token_hash', 64)->unique();
                $table->json('abilities_json')->nullable();
                $table->json('context_json')->nullable();
                $table->string('last_ip', 45)->nullable();
                $table->text('last_user_agent')->nullable();
                $table->dateTime('last_used_at')->nullable();
                $table->dateTime('expires_at')->nullable();
                $table->dateTime('revoked_at')->nullable();
                $table->timestamps();

                $table->index(['client_portal_user_id', 'revoked_at'], 'idx_portal_api_tokens_user_revoked');
                $table->index(['expires_at'], 'idx_portal_api_tokens_expires_at');
            });
        }

        if (!Schema::hasTable('client_portal_device_tokens')) {
            Schema::create('client_portal_device_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_portal_user_id')->constrained('client_portal_users')->cascadeOnDelete();
                $table->foreignId('client_portal_api_token_id')->nullable()->constrained('client_portal_api_tokens')->nullOnDelete();
                $table->longText('fcm_token');
                $table->string('fcm_token_hash', 64)->unique();
                $table->string('platform', 20)->default('android');
                $table->string('device_name', 160)->nullable();
                $table->string('app_version', 40)->nullable();
                $table->dateTime('last_seen_at')->nullable();
                $table->dateTime('revoked_at')->nullable();
                $table->timestamps();

                $table->index(['client_portal_user_id', 'revoked_at'], 'idx_portal_device_tokens_user_revoked');
                $table->index(['client_portal_api_token_id', 'revoked_at'], 'idx_portal_device_tokens_api_revoked');
            });
        }

        if (!Schema::hasTable('client_portal_notifications')) {
            Schema::create('client_portal_notifications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_portal_user_id')->constrained('client_portal_users')->cascadeOnDelete();
                $table->foreignId('client_condominium_id')->nullable()->constrained('client_condominiums')->nullOnDelete();
                $table->string('type', 80);
                $table->string('title', 180);
                $table->text('body');
                $table->json('data')->nullable();
                $table->dateTime('read_at')->nullable();
                $table->dateTime('sent_at')->nullable();
                $table->dateTime('failed_at')->nullable();
                $table->text('failure_reason')->nullable();
                $table->timestamps();

                $table->index(['client_portal_user_id', 'read_at'], 'idx_portal_notifications_user_read');
                $table->index(['type', 'created_at'], 'idx_portal_notifications_type_created');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portal_notifications');
        Schema::dropIfExists('client_portal_device_tokens');
        Schema::dropIfExists('client_portal_api_tokens');
    }
};
