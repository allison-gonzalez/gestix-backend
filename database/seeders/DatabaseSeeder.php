<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Usuario de prueba estándar
        User::create([
            'nombre' => 'Test User',
            'correo' => 'test@gestix.com',
            'contrasena' => Hash::make('password123'),
            'estatus' => 1,
        ]);

        // Usuario Administrador
        User::create([
            'nombre' => 'Admin User',
            'correo' => 'admin@gestix.com',
            'contrasena' => Hash::make('admin123'),
            'estatus' => 1,
        ]);

        echo "\n¡Seeding completado con éxito!\n";
        echo "- Usuarios creados: " . User::count() . "\n";
    }
}