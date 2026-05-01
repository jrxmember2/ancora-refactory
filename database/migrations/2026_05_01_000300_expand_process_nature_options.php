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

        $names = [
            'Administrativa',
            'Ambiental',
            'Bancaria',
            'Civel',
            'Condominial',
            'Consumidor',
            'Contratual',
            'Digital',
            'Eleitoral',
            'Empresarial',
            'Familia',
            'Imobiliaria',
            'Penal',
            'Previdenciaria',
            'Societaria',
            'Sucessoria',
            'Trabalhista',
            'Tributaria',
        ];

        foreach ($names as $index => $name) {
            DB::table('process_case_options')->updateOrInsert(
                ['group_key' => 'nature', 'slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'color_hex' => null,
                    'is_active' => 1,
                    'sort_order' => $index + 1,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

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
            ->whereIn('slug', [
                'ambiental',
                'bancaria',
                'consumidor',
                'contratual',
                'digital',
                'eleitoral',
                'empresarial',
                'imobiliaria',
                'penal',
                'previdenciaria',
                'societaria',
                'sucessoria',
                'tributaria',
            ])
            ->delete();

        $baseline = [
            'Administrativa',
            'Civel',
            'Condominial',
            'Familia',
            'Trabalhista',
        ];

        foreach ($baseline as $index => $name) {
            DB::table('process_case_options')
                ->where('group_key', 'nature')
                ->where('slug', Str::slug($name))
                ->update([
                    'name' => $name,
                    'sort_order' => $index + 1,
                    'updated_at' => now(),
                ]);
        }
    }
};
