<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_global_documents', function (Blueprint $table) {
            $table->id();
            $table->string('name', 180);
            $table->string('document_type', 40);
            $table->date('document_date');
            $table->string('original_name', 255);
            $table->string('stored_name', 255);
            $table->string('relative_path', 255);
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->longText('extracted_text')->nullable();
            $table->string('processing_status', 20)->default('pending');
            $table->text('processing_error')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('observation')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['document_type', 'is_active']);
            $table->index(['processing_status', 'is_active']);
            $table->index('document_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_global_documents');
    }
};
