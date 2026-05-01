<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('process_case_options')) {
            return;
        }

        DB::table('process_case_options')->updateOrInsert(
            ['group_key' => 'nature', 'slug' => 'criminal'],
            [
                'name' => 'Criminal',
                'color_hex' => null,
                'is_active' => 1,
                'sort_order' => 0,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $ordered = DB::table('process_case_options')
            ->where('group_key', 'nature')
            ->orderBy('name')
            ->get(['id']);

        foreach ($ordered as $index => $option) {
            DB::table('process_case_options')
                ->where('id', $option->id)
                ->update([
                    'sort_order' => $index + 1,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('process_case_options')) {
            return;
        }

        DB::table('process_case_options')
            ->where('group_key', 'nature')
            ->where('slug', 'criminal')
            ->delete();

        $baseline = DB::table('process_case_options')
            ->where('group_key', 'nature')
            ->orderBy('name')
            ->get(['id']);

        foreach ($baseline as $index => $option) {
            DB::table('process_case_options')
                ->where('id', $option->id)
                ->update([
                    'sort_order' => $index + 1,
                    'updated_at' => now(),
                ]);
        }
    }
};
