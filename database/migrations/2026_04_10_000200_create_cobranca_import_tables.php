<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('cobranca_import_batches')) {
            Schema::create('cobranca_import_batches', function (Blueprint $table) {
                $table->id();
                $table->string('original_name', 255);
                $table->string('stored_name', 255);
                $table->string('sheet_name', 180)->nullable();
                $table->string('file_extension', 10)->nullable();
                $table->string('status', 30)->default('parsed');
                $table->unsignedInteger('total_rows')->default(0);
                $table->unsignedInteger('ready_rows')->default(0);
                $table->unsignedInteger('pending_rows')->default(0);
                $table->unsignedInteger('duplicate_rows')->default(0);
                $table->unsignedInteger('created_cases')->default(0);
                $table->unsignedInteger('updated_cases')->default(0);
                $table->unsignedInteger('created_quotas')->default(0);
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->json('summary_json')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at'], 'idx_cobranca_import_batches_status_created');
                $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasTable('cobranca_import_rows')) {
            Schema::create('cobranca_import_rows', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('batch_id');
                $table->unsignedInteger('row_number');
                $table->json('raw_payload_json')->nullable();
                $table->string('condominium_input', 180)->nullable();
                $table->string('block_input', 120)->nullable();
                $table->string('unit_input', 80)->nullable();
                $table->string('reference_input', 30)->nullable();
                $table->string('due_date_input', 40)->nullable();
                $table->decimal('amount_value', 12, 2)->nullable();
                $table->integer('matched_unit_id')->nullable();
                $table->unsignedBigInteger('matched_case_id')->nullable();
                $table->string('status', 30)->default('ready');
                $table->string('message', 255)->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();

                $table->index(['batch_id', 'status'], 'idx_cobranca_import_rows_batch_status');
                $table->index(['matched_unit_id', 'reference_input', 'due_date_input'], 'idx_cobranca_import_rows_match_ref_due');
                $table->foreign('batch_id')->references('id')->on('cobranca_import_batches')->cascadeOnDelete();
                $table->foreign('matched_unit_id')->references('id')->on('client_units')->nullOnDelete();
                $table->foreign('matched_case_id')->references('id')->on('cobranca_cases')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranca_import_rows');
        Schema::dropIfExists('cobranca_import_batches');
    }
};
