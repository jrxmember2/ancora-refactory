<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users') || Schema::hasColumn('users', 'calendar_feed_token')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('calendar_feed_token', 64)->nullable()->unique()->after('is_active');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'calendar_feed_token')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('calendar_feed_token');
        });
    }
};
