<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Canais de lembrete + destinatario de copia, direto no evento.
        if (Schema::hasTable('agenda_events')) {
            Schema::table('agenda_events', function (Blueprint $table) {
                if (!Schema::hasColumn('agenda_events', 'remind_email')) {
                    $table->boolean('remind_email')->default(true)->after('reminder_sent_at');
                }
                if (!Schema::hasColumn('agenda_events', 'remind_whatsapp')) {
                    $table->boolean('remind_whatsapp')->default(true)->after('remind_email');
                }
                if (!Schema::hasColumn('agenda_events', 'copy_enabled')) {
                    $table->boolean('copy_enabled')->default(false)->after('remind_whatsapp');
                }
                if (!Schema::hasColumn('agenda_events', 'copy_name')) {
                    $table->string('copy_name', 160)->nullable()->after('copy_enabled');
                }
                if (!Schema::hasColumn('agenda_events', 'copy_phone')) {
                    $table->string('copy_phone', 30)->nullable()->after('copy_name');
                }
                if (!Schema::hasColumn('agenda_events', 'copy_email')) {
                    $table->string('copy_email', 190)->nullable()->after('copy_phone');
                }
            });
        }

        // Multiplos lembretes por evento (cada linha dispara na sua propria janela).
        if (!Schema::hasTable('agenda_event_reminders')) {
            Schema::create('agenda_event_reminders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('agenda_event_id');
                $table->unsignedInteger('minutes_before');
                $table->dateTime('sent_at')->nullable();
                $table->timestamps();

                $table->index('agenda_event_id', 'idx_agenda_reminder_event');
                $table->unique(['agenda_event_id', 'minutes_before'], 'uq_agenda_reminder');
                $table->foreign('agenda_event_id')->references('id')->on('agenda_events')->cascadeOnDelete();
            });
        }

        // Migra o lembrete unico legado (reminder_minutes) para a nova tabela.
        if (Schema::hasTable('agenda_event_reminders')
            && Schema::hasTable('agenda_events')
            && Schema::hasColumn('agenda_events', 'reminder_minutes')) {
            $legacy = DB::table('agenda_events')
                ->whereNotNull('reminder_minutes')
                ->where('reminder_minutes', '>', 0)
                ->get(['id', 'reminder_minutes', 'reminder_sent_at']);

            foreach ($legacy as $event) {
                $exists = DB::table('agenda_event_reminders')
                    ->where('agenda_event_id', $event->id)
                    ->where('minutes_before', (int) $event->reminder_minutes)
                    ->exists();

                if (!$exists) {
                    DB::table('agenda_event_reminders')->insert([
                        'agenda_event_id' => $event->id,
                        'minutes_before' => (int) $event->reminder_minutes,
                        'sent_at' => $event->reminder_sent_at,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_event_reminders');

        if (Schema::hasTable('agenda_events')) {
            Schema::table('agenda_events', function (Blueprint $table) {
                foreach (['copy_email', 'copy_phone', 'copy_name', 'copy_enabled', 'remind_whatsapp', 'remind_email'] as $column) {
                    if (Schema::hasColumn('agenda_events', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
