<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Usuario extends Model
{
    protected $connection = 'mongodb';
    protected $collection = 'usuarios';

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
    ];

    protected $casts = [
        'estatus' => 'integer',
        'departamento_id' => 'integer',
        'permisos' => 'array',
    ];

    /**
     * Obtener el departamento del usuario
     */
    public function departamento()
    {
        return $this->belongsTo(Departamento::class, 'departamento_id', 'id');
    }

    /**
     * Obtener los permisos del usuario
     */
    public function permisosRelacion()
    {
        return $this->hasMany(Permiso::class, '_id', 'permisos[0]');
    }

    /**
     * Obtener los tickets creados por el usuario
     */
    public function tickets()
    {
        return $this->hasMany(Ticket::class, 'usuario_autor_id', 'id');
    }

    /**
     * Obtener los comentarios del usuario
     */
    public function comentarios()
    {
        return $this->hasMany(Comentario::class, 'usuario_autor_id', 'id');
    }

    /**
     * Verificar si el usuario tiene un permiso específico
     */
    public function tienePermiso($permiso_id)
    {
        return in_array($permiso_id, $this->permisos ?? []);
    }

    /**
     * Hash de contraseña (si usas autenticación)
     */
    public function setContraseñaAttribute($value)
    {
        $this->attributes['contrasena'] = bcrypt($value);
    }
}
