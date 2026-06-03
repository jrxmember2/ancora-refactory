<?php

use App\Support\AiDocumentCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_document_chunks')) {
            return;
        }

        Schema::table('ai_document_chunks', function (Blueprint $table) {
            if (!Schema::hasColumn('ai_document_chunks', 'client_condominium_id')) {
                $table->integer('client_condominium_id')->nullable()->after('source_type');
            }

            if (!Schema::hasColumn('ai_document_chunks', 'document_kind')) {
                $table->string('document_kind', 60)->nullable()->after('ai_global_document_id');
            }

            if (!Schema::hasColumn('ai_document_chunks', 'document_date')) {
                $table->date('document_date')->nullable()->after('document_kind');
            }

            if (!Schema::hasColumn('ai_document_chunks', 'chunk_index')) {
                $table->unsignedInteger('chunk_index')->default(1)->after('document_date');
            }

            if (!Schema::hasColumn('ai_document_chunks', 'title')) {
                $table->string('title', 255)->nullable()->after('chunk_index');
            }

            if (!Schema::hasColumn('ai_document_chunks', 'content')) {
                $table->longText('content')->nullable()->after('title');
            }

            if (!Schema::hasColumn('ai_document_chunks', 'searchable_content')) {
                $table->longText('searchable_content')->nullable()->after('content');
            }
        });

        DB::table('ai_document_chunks')
            ->whereNull('source_type')
            ->whereNotNull('client_attachment_id')
            ->update(['source_type' => AiDocumentCatalog::SOURCE_CONDOMINIUM_ATTACHMENT]);

        DB::table('ai_document_chunks')
            ->whereNull('source_type')
            ->whereNotNull('ai_global_document_id')
            ->update(['source_type' => AiDocumentCatalog::SOURCE_GLOBAL_DOCUMENT]);

        DB::statement('UPDATE ai_document_chunks SET client_condominium_id = condominium_id WHERE client_condominium_id IS NULL AND condominium_id IS NOT NULL');
        DB::statement('UPDATE ai_document_chunks SET document_kind = source_document_type WHERE document_kind IS NULL AND source_document_type IS NOT NULL');
        DB::statement('UPDATE ai_document_chunks SET chunk_index = chunk_order WHERE (chunk_index IS NULL OR chunk_index = 1) AND chunk_order IS NOT NULL');
        DB::statement('UPDATE ai_document_chunks SET title = reference_label WHERE title IS NULL AND reference_label IS NOT NULL');
        DB::statement('UPDATE ai_document_chunks SET content = chunk_text WHERE content IS NULL AND chunk_text IS NOT NULL');

        // Backfill com UPDATE ... JOIN (sintaxe MySQL). Em outros drivers (ex.: SQLite nos testes)
        // nao ha dados legados a preencher, entao e ignorado com seguranca.
        if (DB::getDriverName() === 'mysql') {
            if (Schema::hasTable('ai_global_documents')) {
                DB::statement('UPDATE ai_document_chunks c INNER JOIN ai_global_documents g ON g.id = c.ai_global_document_id SET c.document_date = g.document_date WHERE c.document_date IS NULL');
            }

            if (Schema::hasTable('client_attachments') && Schema::hasColumn('client_attachments', 'document_date')) {
                DB::statement('UPDATE ai_document_chunks c INNER JOIN client_attachments a ON a.id = c.client_attachment_id SET c.document_date = a.document_date WHERE c.document_date IS NULL');
            }
        }

        DB::table('ai_document_chunks')
            ->select(['id', 'content', 'chunk_text'])
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $content = trim((string) ($row->content ?: $row->chunk_text ?: ''));
                    $searchable = $content !== '' ? AiDocumentCatalog::searchableText($content) : null;

                    DB::table('ai_document_chunks')
                        ->where('id', (int) $row->id)
                        ->update([
                            'searchable_content' => $searchable,
                        ]);
                }
            });

        try {
            Schema::table('ai_document_chunks', function (Blueprint $table) {
                $table->index(['source_type', 'client_condominium_id', 'is_active'], 'idx_ai_chunks_source_condo_active');
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('ai_document_chunks', function (Blueprint $table) {
                $table->index(['client_attachment_id', 'is_active'], 'idx_ai_chunks_attachment_active');
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('ai_document_chunks', function (Blueprint $table) {
                $table->index(['ai_global_document_id', 'is_active'], 'idx_ai_chunks_global_active');
            });
        } catch (\Throwable) {
        }

        try {
            Schema::table('ai_document_chunks', function (Blueprint $table) {
                $table->foreign('client_condominium_id', 'fk_ai_chunks_client_condominium')
                    ->references('id')
                    ->on('client_condominiums')
                    ->nullOnDelete();
            });
        } catch (\Throwable) {
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('ai_document_chunks')) {
            return;
        }

        Schema::table('ai_document_chunks', function (Blueprint $table) {
            if (Schema::hasColumn('ai_document_chunks', 'client_condominium_id')) {
                try {
                    $table->dropForeign('fk_ai_chunks_client_condominium');
                } catch (\Throwable) {
                }
            }

            try {
                $table->dropIndex('idx_ai_chunks_source_condo_active');
            } catch (\Throwable) {
            }

            try {
                $table->dropIndex('idx_ai_chunks_attachment_active');
            } catch (\Throwable) {
            }

            try {
                $table->dropIndex('idx_ai_chunks_global_active');
            } catch (\Throwable) {
            }

            foreach (['searchable_content', 'content', 'title', 'chunk_index', 'document_date', 'document_kind', 'client_condominium_id'] as $column) {
                if (Schema::hasColumn('ai_document_chunks', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
