<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hub_app_login_logs')) {
            return;
        }

        Schema::create('hub_app_login_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('hub_api_token_id')->nullable()->constrained('hub_api_tokens')->nullOnDelete();
            $table->string('platform', 20)->nullable();
            $table->string('device_name', 160)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->boolean('success')->default(true);
            $table->string('failure_reason', 190)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'idx_hub_app_login_logs_user_created');
            $table->index(['platform', 'created_at'], 'idx_hub_app_login_logs_platform_created');
            $table->index(['success', 'created_at'], 'idx_hub_app_login_logs_success_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_app_login_logs');
    }
};
