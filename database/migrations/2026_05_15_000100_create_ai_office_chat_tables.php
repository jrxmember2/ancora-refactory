<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_office_chat_conversations')) {
            Schema::create('ai_office_chat_conversations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unsignedInteger('client_condominium_id')->nullable();
                $table->string('scope_type', 30)->default('condominium');
                $table->string('title', 180)->nullable();
                $table->string('status', 30)->default('active');
                $table->dateTime('last_message_at')->nullable();
                $table->string('last_provider', 30)->nullable();
                $table->string('last_model', 120)->nullable();
                $table->timestamps();

                $table->index(['user_id', 'last_message_at'], 'idx_ai_office_chat_conversations_user_last_message');
                $table->index(['client_condominium_id', 'last_message_at'], 'idx_ai_office_chat_conversations_condo_last_message');
                $table->index(['scope_type', 'status'], 'idx_ai_office_chat_conversations_scope_status');

                $table->foreign('client_condominium_id', 'fk_ai_office_chat_conversations_condominium')
                    ->references('id')
                    ->on('client_condominiums')
                    ->nullOnDelete();
            });
        }

        if (!Schema::hasTable('ai_office_chat_messages')) {
            Schema::create('ai_office_chat_messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ai_office_chat_conversation_id')->constrained('ai_office_chat_conversations')->cascadeOnDelete();
                $table->string('role', 20);
                $table->longText('content');
                $table->string('status', 20)->default('success');
                $table->string('provider', 30)->nullable();
                $table->string('model', 120)->nullable();
                $table->unsignedInteger('source_chunks_count')->default(0);
                $table->unsignedInteger('token_estimate')->nullable();
                $table->unsignedInteger('input_tokens')->nullable();
                $table->unsignedInteger('output_tokens')->nullable();
                $table->unsignedInteger('tokens_total')->nullable();
                $table->text('error_message')->nullable();
                $table->longText('meta_json')->nullable();
                $table->timestamps();

                $table->index(['ai_office_chat_conversation_id', 'created_at'], 'idx_ai_office_chat_messages_conversation_created');
                $table->index(['role', 'status'], 'idx_ai_office_chat_messages_role_status');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_office_chat_messages');
        Schema::dropIfExists('ai_office_chat_conversations');
    }
};
