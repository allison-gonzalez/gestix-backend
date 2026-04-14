<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Cambiamos 'users' por 'usuarios' y las columnas a español
        Schema::create('usuarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('correo')->unique();
            $table->string('telefono')->nullable(); // Agregado para el perfil
            $table->timestamp('email_verified_at')->nullable();
            $table->string('contrasena'); // En español
            $table->integer('estatus')->default(1); // Agregado para el sistema
            $table->integer('departamento_id')->nullable(); // Agregado
            $table->text('permisos')->nullable(); // Agregado
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuarios'); // Corregido el nombre aquí también
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};