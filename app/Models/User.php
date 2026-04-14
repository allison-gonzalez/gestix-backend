<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'usuarios';  

    protected $fillable = [
        'nombre',
        'correo',
        'telefono',
        'contrasena',
        'estatus',
        'departamento_id',
        'permisos',
    ];

    protected $hidden = [
        'contrasena',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'estatus' => 'integer',
            'departamento_id' => 'integer',
        ];
    }

    /**
     * Retorna la columna usada para la contraseña.
     */
    public function getAuthPassword()
    {
        return $this->contrasena;
    }
}