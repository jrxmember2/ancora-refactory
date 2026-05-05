<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('electronic_signature_documents')) {
            Schema::create('electronic_signature_documents', function (Blueprint $table) {
                $table->id();
                $table->string('uuid', 80)->unique();
                $table->string('title', 255);
                $table->text('description')->nullable();
                $table->string('category', 120)->nullable();
                $table->string('status', 60)->default('draft');
                $table->string('original_name', 255);
                $table->string('stored_name', 255)->nullable();
                $table->string('local_pdf_path', 255);
                $table->string('mime_type', 120)->default('application/pdf');
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('sha256_hash', 64)->nullable();
                $table->integer('client_entity_id')->nullable();
                $table->integer('client_condominium_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->softDeletes();
                $table->timestamps();

                $table->index('status', 'idx_esign_docs_status');
                $table->index('created_by', 'idx_esign_docs_created_by');
                $table->index('client_entity_id', 'idx_esign_docs_client_entity');
                $table->index('client_condominium_id', 'idx_esign_docs_client_condo');
            });
        }

        $this->repairForeignKeys();
    }

    public function down(): void
    {
        if (!Schema::hasTable('electronic_signature_documents')) {
            return;
        }

        $this->dropForeignIfExists('electronic_signature_documents', 'fk_esign_docs_created_by');
        $this->dropForeignIfExists('electronic_signature_documents', 'fk_esign_docs_updated_by');
        $this->dropForeignIfExists('electronic_signature_documents', 'fk_esign_docs_client_entity');
        $this->dropForeignIfExists('electronic_signature_documents', 'fk_esign_docs_client_condo');

        Schema::dropIfExists('electronic_signature_documents');
    }

    private function repairForeignKeys(): void
    {
        if (!Schema::hasTable('electronic_signature_documents')) {
            return;
        }

        $this->dropForeignIfExists('electronic_signature_documents', 'fk_esign_docs_created_by');
        $this->dropForeignIfExists('electronic_signature_documents', 'fk_esign_docs_updated_by');
        $this->dropForeignIfExists('electronic_signature_documents', 'fk_esign_docs_client_entity');
        $this->dropForeignIfExists('electronic_signature_documents', 'fk_esign_docs_client_condo');

        if (Schema::hasTable('client_entities') && Schema::hasColumn('electronic_signature_documents', 'client_entity_id')) {
            $referenceType = $this->referenceColumnType('client_entities', 'id') ?: 'INT';
            DB::statement("ALTER TABLE electronic_signature_documents MODIFY client_entity_id {$referenceType} NULL");

            if (!$this->foreignKeyExists('electronic_signature_documents', 'fk_esign_docs_client_entity')) {
                DB::statement('ALTER TABLE electronic_signature_documents ADD CONSTRAINT fk_esign_docs_client_entity FOREIGN KEY (client_entity_id) REFERENCES client_entities(id) ON DELETE SET NULL');
            }
        }

        if (Schema::hasTable('client_condominiums') && Schema::hasColumn('electronic_signature_documents', 'client_condominium_id')) {
            $referenceType = $this->referenceColumnType('client_condominiums', 'id') ?: 'INT';
            DB::statement("ALTER TABLE electronic_signature_documents MODIFY client_condominium_id {$referenceType} NULL");

            if (!$this->foreignKeyExists('electronic_signature_documents', 'fk_esign_docs_client_condo')) {
                DB::statement('ALTER TABLE electronic_signature_documents ADD CONSTRAINT fk_esign_docs_client_condo FOREIGN KEY (client_condominium_id) REFERENCES client_condominiums(id) ON DELETE SET NULL');
            }
        }

        if (Schema::hasTable('users') && Schema::hasColumn('electronic_signature_documents', 'created_by') && !$this->foreignKeyExists('electronic_signature_documents', 'fk_esign_docs_created_by')) {
            DB::statement('ALTER TABLE electronic_signature_documents ADD CONSTRAINT fk_esign_docs_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL');
        }

        if (Schema::hasTable('users') && Schema::hasColumn('electronic_signature_documents', 'updated_by') && !$this->foreignKeyExists('electronic_signature_documents', 'fk_esign_docs_updated_by')) {
            DB::statement('ALTER TABLE electronic_signature_documents ADD CONSTRAINT fk_esign_docs_updated_by FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL');
        }
    }

    private function dropForeignIfExists(string $table, string $constraint): void
    {
        if ($this->foreignKeyExists($table, $constraint)) {
            DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$constraint}");
        }
    }

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('CONSTRAINT_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }

    private function referenceColumnType(string $table, string $column): ?string
    {
        return DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->value('COLUMN_TYPE');
    }
};
