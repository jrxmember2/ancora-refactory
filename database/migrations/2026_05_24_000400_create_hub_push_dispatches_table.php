<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hub_push_dispatches')) {
            return;
        }

        Schema::create('hub_push_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hub_notification_id')->nullable()->constrained('hub_notifications')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('hub_device_token_id')->nullable()->constrained('hub_device_tokens')->nullOnDelete();
            $table->string('title', 180);
            $table->text('body');
            $table->json('data_json')->nullable();
            $table->string('status', 30)->default('pending');
            $table->text('error_message')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at'], 'idx_hub_push_dispatches_user_status_created');
            $table->index(['hub_notification_id', 'status'], 'idx_hub_push_dispatches_notification_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_push_dispatches');
    }
};
