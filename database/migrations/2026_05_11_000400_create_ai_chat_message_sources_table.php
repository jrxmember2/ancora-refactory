<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_chat_message_sources')) {
            Schema::create('ai_chat_message_sources', function (Blueprint $table) {
                $table->id();
                $table->foreignId('ai_chat_message_id')->constrained('ai_chat_messages')->cascadeOnDelete();
                $table->string('source_type', 40);
                $table->integer('client_attachment_id')->nullable();
                $table->foreignId('ai_global_document_id')->nullable()->constrained('ai_global_documents')->nullOnDelete();
                $table->unsignedBigInteger('chunk_id')->nullable();
                $table->string('document_title', 255)->nullable();
                $table->string('document_kind', 80)->nullable();
                $table->timestamps();

                $table->index(['ai_chat_message_id', 'source_type'], 'idx_ai_chat_message_sources_message_source');
                $table->index(['client_attachment_id'], 'idx_ai_chat_message_sources_attachment');
                $table->index(['ai_global_document_id'], 'idx_ai_chat_message_sources_global_document');
                $table->index(['chunk_id'], 'idx_ai_chat_message_sources_chunk');

                $table->foreign('client_attachment_id', 'fk_ai_chat_message_sources_attachment')
                    ->references('id')
                    ->on('client_attachments')
                    ->nullOnDelete();

                $table->foreign('chunk_id', 'fk_ai_chat_message_sources_chunk')
                    ->references('id')
                    ->on('ai_document_chunks')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_message_sources');
    }
};
