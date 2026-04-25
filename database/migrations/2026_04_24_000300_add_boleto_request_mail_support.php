<?php

use App\Support\AncoraRouteCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('client_entities') && !Schema::hasColumn('client_entities', 'cobranca_emails_json')) {
            Schema::table('client_entities', function (Blueprint $table) {
                $table->json('cobranca_emails_json')->nullable()->after('emails_json');
            });
        }

        if (!Schema::hasTable('cobranca_case_email_histories')) {
            Schema::create('cobranca_case_email_histories', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('cobranca_case_id');
                $table->unsignedBigInteger('cobranca_monetary_update_id')->nullable();
                $table->unsignedBigInteger('sent_by')->nullable();
                $table->string('from_address', 190)->nullable();
                $table->string('from_name', 190)->nullable();
                $table->string('subject', 255);
                $table->json('recipients_json')->nullable();
                $table->longText('body_html')->nullable();
                $table->string('attachment_original_name', 255)->nullable();
                $table->string('attachment_stored_name', 255)->nullable();
                $table->string('attachment_relative_path', 255)->nullable();
                $table->string('attachment_mime_type', 120)->nullable();
                $table->unsignedBigInteger('attachment_file_size')->default(0);
                $table->string('send_status', 30)->default('pending');
                $table->text('transport_message')->nullable();
                $table->string('imap_status', 30)->nullable();
                $table->text('imap_message')->nullable();
                $table->timestamps();

                $table->index(['cobranca_case_id', 'created_at'], 'idx_cobranca_case_email_histories_case_created');
                $table->index(['cobranca_monetary_update_id'], 'idx_cobranca_case_email_histories_update');
                $table->foreign('cobranca_case_id', 'fk_cobranca_case_email_histories_case')
                    ->references('id')->on('cobranca_cases')->cascadeOnDelete();
                $table->foreign('cobranca_monetary_update_id', 'fk_cobranca_case_email_histories_update')
                    ->references('id')->on('cobranca_monetary_updates')->nullOnDelete();
                $table->foreign('sent_by', 'fk_cobranca_case_email_histories_user')
                    ->references('id')->on('users')->nullOnDelete();
            });
        }

        $this->syncRoutePermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('cobranca_case_email_histories');

        if (Schema::hasTable('client_entities') && Schema::hasColumn('client_entities', 'cobranca_emails_json')) {
            Schema::table('client_entities', function (Blueprint $table) {
                $table->dropColumn('cobranca_emails_json');
            });
        }
    }

    private function syncRoutePermissions(): void
    {
        if (!Schema::hasTable('route_permissions')) {
            return;
        }

        foreach (AncoraRouteCatalog::groups() as $groupKey => $group) {
            foreach ($group['routes'] as $routeName => $label) {
                DB::table('route_permissions')->updateOrInsert(
                    ['route_name' => $routeName],
                    ['group_key' => $groupKey, 'label' => $label, 'created_at' => now()]
                );
            }
        }
    }
};
