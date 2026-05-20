<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_portal_push_dispatches')) {
            return;
        }

        Schema::create('client_portal_push_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 180);
            $table->text('body');
            $table->string('notification_type', 40);
            $table->string('recipient_mode', 20)->default('global');
            $table->json('recipient_user_ids_json')->nullable();
            $table->json('recipient_snapshots_json')->nullable();
            $table->json('payload_json')->nullable();
            $table->string('status', 30)->default('queued');
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->unsignedInteger('invalid_token_count')->default(0);
            $table->dateTime('queued_at')->nullable();
            $table->dateTime('processing_started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'idx_portal_push_dispatch_status_created');
            $table->index(['notification_type', 'created_at'], 'idx_portal_push_dispatch_type_created');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('client_portal_push_dispatches');
    }
};
