<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('client_portal_users')) {
            return;
        }

        Schema::table('client_portal_users', function (Blueprint $table) {
            if (!Schema::hasColumn('client_portal_users', 'birth_date')) {
                $table->date('birth_date')->nullable()->after('phone');
            }

            if (!Schema::hasColumn('client_portal_users', 'avatar_path')) {
                $table->string('avatar_path', 255)->nullable()->after('birth_date');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('client_portal_users')) {
            return;
        }

        Schema::table('client_portal_users', function (Blueprint $table) {
            if (Schema::hasColumn('client_portal_users', 'avatar_path')) {
                $table->dropColumn('avatar_path');
            }

            if (Schema::hasColumn('client_portal_users', 'birth_date')) {
                $table->dropColumn('birth_date');
            }
        });
    }
};
