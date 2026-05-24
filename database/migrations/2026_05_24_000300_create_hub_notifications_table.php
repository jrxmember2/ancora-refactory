<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hub_notifications')) {
            return;
        }

        Schema::create('hub_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 180);
            $table->text('body');
            $table->string('type', 80)->nullable();
            $table->string('module', 80)->nullable();
            $table->string('entity_type', 160)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('action_url', 255)->nullable();
            $table->json('data_json')->nullable();
            $table->dateTime('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at'], 'idx_hub_notifications_user_read');
            $table->index(['module', 'created_at'], 'idx_hub_notifications_module_created');
            $table->index(['entity_type', 'entity_id'], 'idx_hub_notifications_entity_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hub_notifications');
    }
};
