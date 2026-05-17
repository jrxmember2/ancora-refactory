<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_portal_app_login_logs')) {
            return;
        }

        Schema::create('client_portal_app_login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_portal_user_id')->constrained('client_portal_users')->cascadeOnDelete();
            $table->foreignId('client_portal_api_token_id')->nullable()->constrained('client_portal_api_tokens')->nullOnDelete();
            $table->string('platform', 20)->default('android');
            $table->string('device_name', 160)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('country', 80)->nullable();
            $table->string('region', 120)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('location_label', 190)->nullable();
            $table->string('location_source', 60)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['client_portal_user_id', 'created_at'], 'idx_portal_app_login_logs_user_created');
            $table->index(['platform', 'created_at'], 'idx_portal_app_login_logs_platform_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portal_app_login_logs');
    }
};
