<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('agenda_events') || Schema::hasColumn('agenda_events', 'color')) {
            return;
        }

        Schema::table('agenda_events', function (Blueprint $table) {
            $table->string('color', 9)->nullable()->after('priority');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('agenda_events') || !Schema::hasColumn('agenda_events', 'color')) {
            return;
        }

        Schema::table('agenda_events', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }
};
