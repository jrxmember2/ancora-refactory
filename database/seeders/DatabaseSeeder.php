<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->firstOrCreate([
            'email' => 'junior@serratech.br',
        ], [
            'name' => 'Administrador Ancora',
            'password_hash' => password_hash('Ancora@123', PASSWORD_DEFAULT),
            'role' => 'superadmin',
            'theme_preference' => 'dark',
            'is_active' => true,
            'is_protected' => true,
        ]);
    }
}
