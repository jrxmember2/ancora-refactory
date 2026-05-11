<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_document_chunks', function (Blueprint $table) {
            $table->id();
            $table->string('origin', 30);
            $table->string('source_type', 40)->nullable();
            $table->string('source_document_type', 40)->nullable();
            $table->foreignId('ai_global_document_id')->nullable()->constrained('ai_global_documents')->cascadeOnDelete();
            $table->foreignId('client_attachment_id')->nullable()->constrained('client_attachments')->nullOnDelete();
            $table->foreignId('condominium_id')->nullable()->constrained('client_condominiums')->nullOnDelete();
            $table->unsignedInteger('chunk_order')->default(1);
            $table->string('reference_label', 255)->nullable();
            $table->longText('chunk_text');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['origin', 'is_active']);
            $table->index(['ai_global_document_id', 'chunk_order']);
            $table->index(['condominium_id', 'origin', 'is_active']);
            $table->index(['client_attachment_id', 'origin']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_document_chunks');
    }
};
