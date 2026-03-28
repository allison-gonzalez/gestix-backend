<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use MongoDB\Laravel\Eloquent\Model as Eloquent;

class User extends Eloquent implements \Illuminate\Contracts\Auth\Authenticatable
{
    use \Illuminate\Auth\Authenticatable, Notifiable;

    protected $connection = 'mongodb';
    protected $table = 'usuarios';  

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'nombre',
        'correo',
        'telefono',
        'contrasena',
        'estatus',
        'departamento_id',
        'permisos',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'contrasena',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'estatus' => 'integer',
            'departamento_id' => 'integer',
        ];
    }
}
