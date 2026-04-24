<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'avatar_path')) {
                $table->string('avatar_path', 255)->nullable()->after('theme_preference');
            }

            if (!Schema::hasColumn('users', 'last_seen_at')) {
                $table->dateTime('last_seen_at')->nullable()->after('last_login_at');
                $table->index('last_seen_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'last_seen_at')) {
                $table->dropIndex(['last_seen_at']);
                $table->dropColumn('last_seen_at');
            }

            if (Schema::hasColumn('users', 'avatar_path')) {
                $table->dropColumn('avatar_path');
            }
        });
    }
};
