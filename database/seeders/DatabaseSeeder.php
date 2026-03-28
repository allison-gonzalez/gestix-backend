<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    
    public function run(): void
    {
        User::create([
            'name' => 'Test User',
            'email' => 'test@gestix.com',
            'password' => Hash::make('password123'),
        ]);

        User::create([
            'name' => 'Admin User',
            'email' => 'admin@gestix.com',
            'password' => Hash::make('admin123'),
        ]);

        echo "\nSeeding completado!\n";
        echo "- Usuarios: " . User::count() . "\n";
    }
}
