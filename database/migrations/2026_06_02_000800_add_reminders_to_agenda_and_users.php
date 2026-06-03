<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agenda_events') && !Schema::hasColumn('agenda_events', 'reminder_sent_at')) {
            Schema::table('agenda_events', function (Blueprint $table) {
                $table->dateTime('reminder_sent_at')->nullable()->after('reminder_minutes');
            });
        }

        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('phone', 30)->nullable()->after('email');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('agenda_events') && Schema::hasColumn('agenda_events', 'reminder_sent_at')) {
            Schema::table('agenda_events', function (Blueprint $table) {
                $table->dropColumn('reminder_sent_at');
            });
        }

        if (Schema::hasTable('users') && Schema::hasColumn('users', 'phone')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('phone');
            });
        }
    }
};
