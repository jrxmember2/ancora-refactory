<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agenda_events')) {
            Schema::table('agenda_events', function (Blueprint $table) {
                if (!Schema::hasColumn('agenda_events', 'recurrence')) {
                    $table->string('recurrence', 20)->nullable()->after('all_day');
                }
                if (!Schema::hasColumn('agenda_events', 'recurrence_until')) {
                    $table->date('recurrence_until')->nullable()->after('recurrence');
                }
                if (!Schema::hasColumn('agenda_events', 'recurrence_group')) {
                    $table->string('recurrence_group', 80)->nullable()->after('recurrence_until');
                    $table->index('recurrence_group', 'idx_agenda_recurrence_group');
                }
            });
        }

        if (!Schema::hasTable('agenda_event_participants')) {
            Schema::create('agenda_event_participants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('agenda_event_id');
                $table->unsignedBigInteger('user_id');
                $table->timestamps();

                $table->unique(['agenda_event_id', 'user_id'], 'uq_agenda_participant');
                $table->foreign('agenda_event_id')->references('id')->on('agenda_events')->cascadeOnDelete();
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('agenda_event_attachments')) {
            Schema::create('agenda_event_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('agenda_event_id');
                $table->string('original_name', 255);
                $table->string('stored_name', 255);
                $table->string('relative_path', 255);
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('file_size')->default(0);
                $table->unsignedBigInteger('uploaded_by')->nullable();
                $table->timestamps();

                $table->index('agenda_event_id', 'idx_agenda_attachment_event');
                $table->foreign('agenda_event_id')->references('id')->on('agenda_events')->cascadeOnDelete();
                $table->foreign('uploaded_by')->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_event_attachments');
        Schema::dropIfExists('agenda_event_participants');

        if (Schema::hasTable('agenda_events')) {
            Schema::table('agenda_events', function (Blueprint $table) {
                foreach (['recurrence_group', 'recurrence_until', 'recurrence'] as $column) {
                    if (Schema::hasColumn('agenda_events', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
